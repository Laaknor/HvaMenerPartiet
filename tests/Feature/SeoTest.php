<?php

namespace Tests\Feature;

use App\Digitalisering\Dokument;
use App\Digitalisering\DokumentAvsnitt;
use App\Digitalisering\DokumentKapittel;
use App\Digitalisering\ProgramLagrer;
use App\Enums\Kildetype;
use App\Enums\Sikkerhet;
use App\Enums\Standpunktstatus;
use App\Enums\Standpunktverdi;
use App\Models\Parti;
use App\Models\Program;
use App\Models\Setning;
use App\Models\Standpunkt;
use App\Models\Tema;
use App\Seo\Side;
use App\Seo\Sitemapkilde;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['seo.indekser' => true, 'app.url' => 'https://hvamenerpartiet.no']);
        url()->forceRootUrl('https://hvamenerpartiet.no');
        url()->forceScheme('https');
    }

    public function test_robots_txt_slipper_inn_alle_men_sperrer_admin_og_horizon(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("User-agent: *\nAllow: /", false)
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /horizon')
            ->assertSee('Sitemap: https://hvamenerpartiet.no/sitemap.xml');
    }

    public function test_robots_txt_sperrer_alt_utenfor_produksjon(): void
    {
        config(['seo.indekser' => false]);

        $this->get('/robots.txt')
            ->assertSee("User-agent: *\nDisallow: /", false)
            ->assertDontSee('Sitemap:')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_sitemap_har_offentlige_sider_men_ikke_admin_eller_tekniske_filer(): void
    {
        $xml = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('<loc>https://hvamenerpartiet.no</loc>', $xml);
        $this->assertStringContainsString('<priority>1.0</priority>', $xml);
        $this->assertStringNotContainsString('admin', $xml);
        $this->assertStringNotContainsString('horizon', $xml);
        $this->assertStringNotContainsString('robots.txt', $xml);
        $this->assertStringNotContainsString('llms', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_nye_sider_kommer_automatisk_med_i_sitemap_og_llms_txt(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('om', fn () => 'Om')->sitemap(tittel: 'Om tjenesten', beskrivelse: 'Metode og kilder');
            Route::get('metode', fn () => 'Metode');
            Route::get('tema/{tema}', fn () => 'Tema')->sitemap(TestTemasider::class);
            Route::get('uten-kilde/{x}', fn () => 'X');
            Route::get('skjult', fn () => 'Skjult')->utenforSitemap();
        });

        $xml = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('<loc>https://hvamenerpartiet.no/om</loc>', $xml);
        $this->assertStringContainsString('<loc>https://hvamenerpartiet.no/metode</loc>', $xml);
        $this->assertStringContainsString('<loc>https://hvamenerpartiet.no/tema/nato</loc>', $xml);
        $this->assertStringContainsString('<lastmod>2026-10-01T12:00:00+00:00</lastmod>', $xml);
        $this->assertStringNotContainsString('uten-kilde', $xml);
        $this->assertStringNotContainsString('skjult', $xml);

        $this->get('/llms.txt')
            ->assertSee('- [Om tjenesten](https://hvamenerpartiet.no/om): Metode og kilder', false)
            ->assertSee('- [NATO](https://hvamenerpartiet.no/tema/nato): Hva mener partiene om NATO?', false);
    }

    public function test_llms_txt_folger_formatet(): void
    {
        $tekst = $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->getContent();

        $this->assertStringStartsWith("# HvaMenerPartiet\n\n> ", $tekst);
        $this->assertStringContainsString("## Sider\n\n- [Forside](https://hvamenerpartiet.no)", $tekst);
        $this->assertStringContainsString('(https://hvamenerpartiet.no/llms-full.txt)', $tekst);
        $this->assertStringContainsString('(https://github.com/Laaknor/HvaMenerPartiet)', $tekst);
    }

    public function test_llms_full_txt_viser_bare_godkjente_standpunkter_for_publiserte_temaer(): void
    {
        $parti = Parti::create(['navn' => 'Testpartiet', 'forkortelse' => 'TP', 'slug' => 'tp']);
        $program = Program::create([
            'parti_id' => $parti->id, 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2025–2029',
            'slug' => 'arbeidsprogram-2025', 'nokkel' => 'tp-ap2025', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
            'gjeldende' => true, 'kilde_url' => 'https://tp.no/program.pdf',
        ]);
        app(ProgramLagrer::class)->lagre($program, new Dokument([
            new DokumentKapittel('1 Forsvar', 1, 1, [new DokumentAvsnitt('Norge skal være med i NATO. Forsvaret skal styrkes.', 4, 1)]),
        ]));

        $lag = function (string $navn, string $status, Standpunktstatus $standpunktstatus) use ($program) {
            $tema = Tema::create(['navn' => $navn, 'slug' => strtolower($navn), 'pastand' => "Norge bør ha {$navn}.", 'status' => $status]);
            $standpunkt = Standpunkt::create([
                'tema_id' => $tema->id, 'program_id' => $program->id, 'kildetype' => Kildetype::Arbeidsprogram,
                'verdi' => Standpunktverdi::For, 'sikkerhet' => Sikkerhet::Hoy, 'status' => $standpunktstatus,
            ]);
            $standpunkt->kildesetninger()->attach(Setning::where('tekst', 'Norge skal være med i NATO.')->value('id'), ['rekkefolge' => 1]);
        };

        $lag('NATO', 'publisert', Standpunktstatus::Godkjent);
        $lag('Upublisert', 'analysert', Standpunktstatus::Godkjent);
        $lag('Maskinelt', 'publisert', Standpunktstatus::Maskinell);

        $this->get('/llms-full.txt')
            ->assertOk()
            ->assertSee('## NATO')
            ->assertSee('Påstand: «Norge bør ha NATO.»')
            ->assertSee('- **Testpartiet (TP)**: For', false)
            ->assertSee('> «Norge skal være med i NATO.»', false)
            ->assertSee('Kilde: [Arbeidsprogram 2025–2029, s. 4](https://tp.no/program.pdf#page=4)', false)
            ->assertDontSee('## Upublisert')
            ->assertSee('## Maskinelt')
            ->assertDontSee("## Maskinelt\n\nPåstand: «Norge bør ha Maskinelt.»\n\n- **Testpartiet", false);
    }

    public function test_security_txt_peker_til_repoet(): void
    {
        $this->travelTo('2026-10-25 12:00:00');

        $this->get('/.well-known/security.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Contact: https://github.com/Laaknor/HvaMenerPartiet/security/advisories/new')
            ->assertSee('Expires: 2027-04-23T00:00:00Z')
            ->assertSee('Policy: https://github.com/Laaknor/HvaMenerPartiet/security/policy')
            ->assertSee('Canonical: https://hvamenerpartiet.no/.well-known/security.txt');

        $this->get('/security.txt')->assertRedirect('/.well-known/security.txt');
    }

    public function test_forsiden_har_metadata_for_sokemotorer_og_deling(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>HvaMenerPartiet – hva mener partiene?</title>', false)
            ->assertSee('<meta name="description" content="Søk på et tema', false)
            ->assertSee('<meta name="robots" content="index, follow', false)
            ->assertSee('<link rel="canonical" href="https://hvamenerpartiet.no">', false)
            ->assertSee('<meta property="og:image" content="https://hvamenerpartiet.no/delingsbilde.png">', false)
            ->assertSee('<meta property="og:locale" content="nb_NO">', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_komponenten_tar_tittel_beskrivelse_og_strukturerte_data(): void
    {
        $html = (string) $this->blade(
            '<x-seo tittel="NATO" beskrivelse="Hva mener partiene om NATO?" :jsonld="$data" />',
            ['data' => [['@type' => 'BreadcrumbList', 'name' => '</script><script>alert(1)</script>']]],
        );

        $this->assertStringContainsString('<title>NATO – HvaMenerPartiet</title>', $html);
        $this->assertStringContainsString('<meta property="og:title" content="NATO">', $html);
        $this->assertStringContainsString('<meta name="description" content="Hva mener partiene om NATO?">', $html);
        $this->assertStringContainsString('"@context":"https://schema.org","@type":"BreadcrumbList"', $html);
        $this->assertStringNotContainsString('</script><script>alert', $html);
    }

    public function test_admin_og_horizon_skal_ikke_indekseres(): void
    {
        $this->get('/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/horizon')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_utenfor_produksjon_skal_ingenting_indekseres(): void
    {
        config(['seo.indekser' => false]);

        $this->get('/')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }
}

class TestTemasider implements Sitemapkilde
{
    public function sider(): iterable
    {
        yield new Side(
            url: url('tema/nato'),
            tittel: 'NATO',
            beskrivelse: 'Hva mener partiene om NATO?',
            sistEndret: new \DateTimeImmutable('2026-10-01 12:00:00+00:00'),
        );
    }
}
