<?php

namespace App\Sidecache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferdig rendrede offentlige sider i cachen.
 *
 * Nøkkelen består av et versjonsnummer, en deployment-ID og URL-en. Å tømme
 * cachen er å øke versjonsnummeret, så det virker med alle cache-stores og
 * koster ett kall. Gamle oppføringer forsvinner av seg selv når levetiden går ut.
 */
class Sidecache
{
    public const CSRF_PLASSHOLDER = '%%sidecache-csrf%%';

    private const VERSJONSNOKKEL = 'sidecache:versjon';

    /** Lagrede headere som er en del av innholdet. Resten lager Laravel på nytt. */
    private const HEADERE = ['content-type', 'content-language', 'x-robots-tag', 'link'];

    private ?string $deployment = null;

    private bool $tomPlanlagt = false;

    public function aktiv(): bool
    {
        return (bool) config('sidecache.aktiv');
    }

    public function store(): Repository
    {
        return Cache::store(config('sidecache.store'));
    }

    /** Kan forespørselen besvares fra, eller lagres i, cachen? */
    public function gjelder(Request $request): bool
    {
        if (! $this->aktiv() || ! $request->isMethodCacheable()) {
            return false;
        }

        if (Str::is(config('seo.ikke_indekser', []), $request->path())) {
            return false;
        }

        // Innloggede (admin) og forespørsler med flash-data (skjemafeil,
        // statusmeldinger) får alltid en fersk side.
        if ($request->headers->has('Authorization') || $request->user()) {
            return false;
        }

        if ($request->hasSession() && filled($request->session()->get('_flash.old'))) {
            return false;
        }

        return true;
    }

    public function kanLagres(Response $response): bool
    {
        if ($response->getStatusCode() !== 200 || $response->getContent() === false) {
            return false;
        }

        $cacheControl = $response->headers->get('Cache-Control', '');
        if (str_contains($cacheControl, 'no-store')) {
            return false;
        }

        $type = (string) $response->headers->get('Content-Type', 'text/html');

        return (bool) preg_match('#^(text/|application/(xml|json|ld\+json|rss\+xml|atom\+xml))#', $type);
    }

    public function nokkel(Request $request): string
    {
        $ignorer = config('sidecache.ignorer_parametere', []);
        $parametere = collect($request->query())
            ->reject(fn ($verdi, $navn) => Str::is($ignorer, (string) $navn))
            ->sortKeys()
            ->all();

        $url = $request->getSchemeAndHttpHost().'/'.ltrim($request->path(), '/').($parametere ? '?'.http_build_query($parametere) : '');

        return 'sidecache:'.$this->versjon().':'.$this->deployment().':'.sha1($url);
    }

    /** @return array{innhold: string, status: int, headere: array<string, list<string>>, etag: string, lagret: int}|null */
    public function hent(Request $request): ?array
    {
        $lagret = $this->store()->get($this->nokkel($request));

        return is_array($lagret) ? $lagret : null;
    }

    /** @return array{innhold: string, status: int, headere: array<string, list<string>>, etag: string, lagret: int} */
    public function lagre(Request $request, Response $response): array
    {
        $innhold = (string) $response->getContent();

        // CSRF-tokenet er per besøkende. Det byttes med en plassholder og
        // settes inn igjen for hver besøkende når siden hentes fra cachen.
        $token = $request->hasSession() ? $request->session()->token() : null;
        if (filled($token)) {
            $innhold = str_replace($token, self::CSRF_PLASSHOLDER, $innhold);
        }

        $oppforing = [
            'innhold' => $innhold,
            'status' => $response->getStatusCode(),
            'headere' => collect($response->headers->all())->only(self::HEADERE)->all(),
            'etag' => 'W/"'.substr(sha1($innhold), 0, 20).'"',
            'lagret' => time(),
        ];

        $this->store()->put($this->nokkel($request), $oppforing, (int) config('sidecache.levetid', 86400));

        return $oppforing;
    }

    /** Lager svaret til den besøkende fra en lagret oppføring. */
    public function svar(Request $request, array $oppforing): Response
    {
        $token = $request->hasSession() ? (string) $request->session()->token() : '';
        $response = new \Illuminate\Http\Response(
            str_replace(self::CSRF_PLASSHOLDER, $token, $oppforing['innhold']),
            $oppforing['status'],
            $oppforing['headere'],
        );

        $response->headers->set('ETag', $oppforing['etag']);
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', $oppforing['lagret']).' GMT');
        $response->headers->set('Age', (string) max(0, time() - $oppforing['lagret']));
        $response->isNotModified($request);

        return $response;
    }

    public function versjon(): int
    {
        return (int) $this->store()->get(self::VERSJONSNOKKEL, 1);
    }

    /** Tømmer cachen for alle offentlige sider. */
    public function tom(): void
    {
        $store = $this->store();
        if (! $store->has(self::VERSJONSNOKKEL)) {
            $store->forever(self::VERSJONSNOKKEL, 1);
        }
        $store->increment(self::VERSJONSNOKKEL);
    }

    /**
     * Tømmer cachen når en eventuell databasetransaksjon er fullført, så ingen
     * rekker å lagre en side med gamle data i mellomtiden. Mange endringer i
     * samme transaksjon gir bare én tømming.
     */
    public function tomEtterCommit(): void
    {
        if (! $this->aktiv()) {
            return;
        }

        if (DB::transactionLevel() === 0) {
            $this->tom();

            return;
        }

        if ($this->tomPlanlagt) {
            return;
        }

        $this->tomPlanlagt = true;
        DB::afterCommit(function () {
            $this->tomPlanlagt = false;
            $this->tom();
        });
    }

    /** Endres ved hver deployment, slik at en ny versjon aldri får gamle sider. */
    public function deployment(): string
    {
        return $this->deployment ??= (string) (config('sidecache.deployment') ?: substr(sha1(implode('|', [
            realpath(base_path()) ?: base_path(),
            $this->gitCommit(),
            @filemtime(public_path('build/manifest.json')) ?: '',
            @filemtime(base_path('composer.lock')) ?: '',
        ])), 0, 12));
    }

    private function gitCommit(): string
    {
        $git = base_path('.git');
        $head = @file_get_contents($git.'/HEAD');
        if ($head === false) {
            return '';
        }

        $head = trim($head);
        if (! str_starts_with($head, 'ref: ')) {
            return $head;
        }

        $ref = substr($head, 5);
        $commit = @file_get_contents($git.'/'.$ref);
        if ($commit !== false) {
            return trim($commit);
        }

        $pakket = @file_get_contents($git.'/packed-refs') ?: '';
        if (preg_match('/^([0-9a-f]{40}) '.preg_quote($ref, '/').'$/m', $pakket, $treff)) {
            return $treff[1];
        }

        return $ref;
    }
}
