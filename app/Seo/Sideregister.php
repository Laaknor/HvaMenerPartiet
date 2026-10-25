<?php

namespace App\Seo;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Finner alle offentlige sider ut fra rutene, så nye sider kommer med i
 * sitemap.xml og llms.txt uten ekstra oppsett:
 *
 * - GET-ruter i web-gruppen uten parametere tas med automatisk.
 * - Ruter med parametere tas med når de oppgir en kilde: ->sitemap(TemaSider::class).
 * - ->sitemap(tittel: '…', beskrivelse: '…') gir tittel og beskrivelse i llms.txt.
 * - ->utenforSitemap() holder en rute utenfor.
 * - Stier i seo.ikke_indekser (admin, Horizon osv.) tas aldri med.
 */
class Sideregister
{
    public function __construct(private readonly Router $router) {}

    /** @return Collection<int, Side> */
    public function sider(): Collection
    {
        $sider = collect();

        foreach ($this->router->getRoutes()->getRoutes() as $rute) {
            if (! $this->erOffentlig($rute)) {
                continue;
            }

            $oppsett = $rute->getAction('sitemap') ?? [];

            if (isset($oppsett['kilde'])) {
                $kilde = app($oppsett['kilde']);
                if (! $kilde instanceof Sitemapkilde) {
                    throw new InvalidArgumentException("{$oppsett['kilde']} må implementere ".Sitemapkilde::class);
                }
                foreach ($kilde->sider() as $side) {
                    $sider->push($side);
                }

                continue;
            }

            if ($rute->parameterNames() !== []) {
                continue;
            }

            $sider->push(new Side(
                url: url($rute->uri()),
                tittel: $oppsett['tittel'] ?? null,
                beskrivelse: $oppsett['beskrivelse'] ?? null,
                endringsfrekvens: $oppsett['endringsfrekvens'] ?? null,
                prioritet: $oppsett['prioritet'] ?? null,
            ));
        }

        return $sider->unique('url')->values();
    }

    private function erOffentlig(Route $rute): bool
    {
        if (! in_array('GET', $rute->methods(), true)) {
            return false;
        }

        if ($rute->getAction('sitemap') === false) {
            return false;
        }

        if (Str::is(config('seo.ikke_indekser', []), $rute->uri())) {
            return false;
        }

        $mellomvare = $rute->gatherMiddleware();

        return in_array('web', $mellomvare, true) && ! in_array('auth', $mellomvare, true)
            && ! collect($mellomvare)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth:'));
    }
}
