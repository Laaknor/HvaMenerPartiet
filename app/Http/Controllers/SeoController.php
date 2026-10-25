<?php

namespace App\Http\Controllers;

use App\Seo\Side;
use App\Seo\Sideregister;
use App\Seo\Standpunkteksport;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** robots.txt, sitemap.xml, llms.txt og security.txt. */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $linjer = ['# '.config('seo.navn'), ''];

        if (! config('seo.indekser')) {
            $linjer = [...$linjer, 'User-agent: *', 'Disallow: /'];
        } else {
            $linjer = [...$linjer, '# Søkemotorer og LLM-er er velkomne. Se også '.url('llms.txt'), 'User-agent: *', 'Allow: /'];
            foreach ($this->sperredeStier() as $sti) {
                $linjer[] = "Disallow: {$sti}";
            }
            $linjer = [...$linjer, '', 'Sitemap: '.url('sitemap.xml')];
        }

        return $this->tekst(implode("\n", $linjer)."\n");
    }

    public function sitemap(Sideregister $register): Response
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($register->sider() as $side) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>'.e($side->url).'</loc>';
            if ($side->sistEndret) {
                $xml[] = '    <lastmod>'.$side->sistEndret->format('c').'</lastmod>';
            }
            if ($side->endringsfrekvens) {
                $xml[] = '    <changefreq>'.e($side->endringsfrekvens).'</changefreq>';
            }
            if ($side->prioritet !== null) {
                $xml[] = '    <priority>'.number_format($side->prioritet, 1, '.', '').'</priority>';
            }
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return response(implode("\n", $xml)."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** https://llmstxt.org */
    public function llms(Sideregister $register): Response
    {
        $md = [
            '# '.config('seo.navn'),
            '',
            '> '.config('seo.beskrivelse'),
            '',
            config('seo.om'),
            '',
            '## Sider',
            '',
        ];

        foreach ($register->sider() as $side) {
            $md[] = $this->lenke($side);
        }

        $md = [
            ...$md,
            '',
            '## Valgfritt',
            '',
            '- ['.config('seo.navn').' i full tekst]('.url('llms-full.txt').'): alle publiserte temaer med hvert partis standpunkt, ordrett utdrag og kilde i én fil',
            '- [Kildekode]('.config('seo.kildekode').'): åpen kildekode (MIT), med metode og designdokument',
        ];

        return $this->tekst(implode("\n", $md)."\n", 'text/markdown');
    }

    public function llmsFull(Standpunkteksport $eksport): Response
    {
        return $this->tekst($eksport->markdown(), 'text/markdown');
    }

    /** RFC 9116 */
    public function securityTxt(): Response
    {
        $sikkerhet = config('seo.sikkerhet');
        $linjer = [];

        foreach ((array) $sikkerhet['kontakt'] as $kontakt) {
            $linjer[] = "Contact: {$kontakt}";
        }
        $linjer[] = 'Expires: '.now()->utc()->startOfDay()->addDays((int) $sikkerhet['gyldig_dager'])->format('Y-m-d\TH:i:s\Z');
        if ($sikkerhet['retningslinjer']) {
            $linjer[] = "Policy: {$sikkerhet['retningslinjer']}";
        }
        $linjer[] = "Preferred-Languages: {$sikkerhet['sprak']}";
        $linjer[] = 'Canonical: '.url('.well-known/security.txt');

        return $this->tekst(implode("\n", $linjer)."\n");
    }

    /** @return list<string> */
    private function sperredeStier(): array
    {
        return collect(config('seo.ikke_indekser', []))
            ->map(fn (string $m) => '/'.Str::before(Str::before($m, '/*'), '*'))
            ->reject(fn (string $sti) => $sti === '/')
            ->unique()
            ->values()
            ->all();
    }

    private function lenke(Side $side): string
    {
        $tittel = $side->tittel ?? (parse_url($side->url, PHP_URL_PATH) ?: '/');
        if ($tittel === '/') {
            $tittel = 'Forside';
        }

        return "- [{$tittel}]({$side->url})".($side->beskrivelse ? ": {$side->beskrivelse}" : '');
    }

    private function tekst(string $innhold, string $type = 'text/plain'): Response
    {
        return response($innhold, 200, ['Content-Type' => "{$type}; charset=UTF-8"]);
    }
}
