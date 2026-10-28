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
use App\Models\Temabesok;
use App\Models\Temaforslag;
use App\Models\Tilbakemelding;
use App\Models\User;
use App\Visning\Kildeutdrag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OffentligeSiderTest extends TestCase
{
    use RefreshDatabase;

    private Tema $nato;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.matomo.host' => 'analytics.example.org', 'services.matomo.site_id' => '1']);

        $this->nato = Tema::create([
            'navn' => 'NATO', 'slug' => 'nato', 'pastand' => 'Norge bør være medlem av NATO.',
            'beskrivelse' => 'Norges medlemskap i forsvarsalliansen.', 'synonymer' => ['forsvarsallianse'], 'status' => 'publisert',
        ]);
    }

    private function parti(string $slug, string $navn): Parti
    {
        return Parti::create(['navn' => $navn, 'forkortelse' => strtoupper($slug), 'slug' => $slug, 'farge' => '#123456']);
    }

    private function program(Parti $parti, string $type, string $tekst, string $status = 'godkjent'): Program
    {
        $ar = $type === 'arbeidsprogram' ? 2025 : 2019;
        $program = Program::create([
            'parti_id' => $parti->id, 'type' => $type, 'tittel' => $type === 'arbeidsprogram' ? 'Arbeidsprogram 2025–2029' : 'Prinsipprogram',
            'slug' => "{$type}-{$ar}", 'nokkel' => "{$parti->slug}-{$type}{$ar}", 'vedtatt_ar' => $ar, 'gjeldende' => true,
            'kilde_url' => "https://{$parti->slug}.no/program.pdf",
        ]);
        app(ProgramLagrer::class)->lagre($program, new Dokument([
            new DokumentKapittel('1 Økonomi', 1, 2, [new DokumentAvsnitt('Vi vil ha lav skatt.', 2, 1)]),
            new DokumentKapittel('2 Forsvar', 1, 7, [
                new DokumentAvsnitt('Verden er urolig.', 7, 1),
                new DokumentAvsnitt($tekst.' Forsvaret skal styrkes.', 7, 1),
            ]),
        ]));
        $program->update(['digitalisering_status' => $status]);

        return $program->fresh();
    }

    private function standpunkt(Program $program, Standpunktverdi $verdi, string $sitat, Standpunktstatus $status = Standpunktstatus::Godkjent, ?Tema $tema = null): Standpunkt
    {
        $standpunkt = Standpunkt::create([
            'tema_id' => ($tema ?? $this->nato)->id, 'program_id' => $program->id,
            'kildetype' => $program->type->value === 'prinsipprogram' ? Kildetype::Prinsipprogram : Kildetype::Arbeidsprogram,
            'verdi' => $verdi, 'sikkerhet' => Sikkerhet::Hoy, 'status' => $status,
        ]);
        $standpunkt->kildesetninger()->attach(Setning::where('program_id', $program->id)->where('tekst', $sitat)->value('id'), ['rekkefolge' => 1]);

        return $standpunkt;
    }

    public function test_forsiden_har_logo_sok_og_publiserte_temaer(): void
    {
        Tema::create(['navn' => 'Skjult', 'slug' => 'skjult', 'pastand' => 'Noe.', 'status' => 'analysert']);

        $this->get('/')
            ->assertOk()
            ->assertSee('HvaMener<span class="logo__aksent">Partiet</span><span class="logo__domene">.no</span>', false)
            ->assertSee('action="'.route('sok').'"', false)
            ->assertSee('<option value="forsvarsallianse">', false)
            ->assertSee('href="'.route('tema', $this->nato).'"', false)
            ->assertDontSee('Skjult')
            ->assertSee('favicon.svg', false)
            ->assertSee("_paq.push(['trackPageView']);", false);
    }

    public function test_temasiden_viser_bare_godkjente_standpunkt_med_ordrett_utdrag_og_lenke_til_setningen(): void
    {
        $h = $this->parti('h', 'Høyre');
        $hAp = $this->program($h, 'arbeidsprogram', 'Norge skal være med i NATO.');
        $this->standpunkt($hAp, Standpunktverdi::For, 'Norge skal være med i NATO.');

        $r = $this->parti('r', 'Rødt');
        $rAp = $this->program($r, 'arbeidsprogram', 'Norge skal ut av NATO.');
        $this->standpunkt($rAp, Standpunktverdi::Mot, 'Norge skal ut av NATO.', Standpunktstatus::Maskinell);

        $this->get('/tema/nato')
            ->assertOk()
            ->assertSee('<title>NATO – HvaMenerPartiet</title>', false)
            ->assertSee('«Norge bør være medlem av NATO.»')
            ->assertSee('«Norge skal være med i NATO.»')
            ->assertSee('href="/program/h/arbeidsprogram-2025/kap-2#a2-s1"', false)
            ->assertSee('Arbeidsprogram 2025–2029, s. 7')
            ->assertSee('data-spor-navn="NATO – Høyre"', false)
            ->assertSee('data-spor-navn="Høyre Arbeidsprogram 2025 s. 7"', false)
            ->assertDontSee('Norge skal ut av NATO.')
            ->assertSee('<strong>Ikke ferdig kontrollert:</strong>', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_prinsipprogram_vises_som_prinsipielt_syn_og_avvik_vises_med_begge_merkelapper(): void
    {
        $sv = $this->parti('sv', 'SV');
        $ap = $this->program($sv, 'arbeidsprogram', 'Vi vil bli i NATO inntil videre.');
        $pp = $this->program($sv, 'prinsipprogram', 'Norge bør ut av NATO.');
        $this->standpunkt($ap, Standpunktverdi::DelvisFor, 'Vi vil bli i NATO inntil videre.');
        $this->standpunkt($pp, Standpunktverdi::Mot, 'Norge bør ut av NATO.');

        $ap2 = $this->parti('ap', 'Arbeiderpartiet');
        $this->standpunkt($this->program($ap2, 'arbeidsprogram', 'NATO er viktig.'), Standpunktverdi::For, 'NATO er viktig.');

        $this->get('/tema/nato')
            ->assertSee('Langsiktig mål avviker fra planen for perioden')
            ->assertSee('Prinsipielt: Mot')
            ->assertSee('«Norge bør ut av NATO.»')
            ->assertSee('Ingen prinsipprogram');

        $this->get('/tema/nato?visning=prinsipprogram')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('<link rel="canonical" href="'.route('tema', $this->nato).'">', false)
            ->assertSee('«Norge bør ut av NATO.»')
            ->assertDontSee('«Vi vil bli i NATO inntil videre.»')
            ->assertSee('Partiet har ikke et gjeldende prinsipprogram.');

        $this->get('/tema/nato?visning=arbeidsprogram')
            ->assertSee('«Vi vil bli i NATO inntil videre.»')
            ->assertDontSee('«Norge bør ut av NATO.»');
    }

    public function test_kombinert_program_uten_prinsippdel_vises_som_ett_samlet_program(): void
    {
        $sp = $this->parti('sp', 'Senterpartiet');
        $kp = $this->program($sp, 'kombinert', 'NATO er sikkerhetsgarantien vår.');
        $kp->update(['tittel' => 'Prinsipp- og handlingsprogram 2025–2029']);
        $this->standpunkt($kp, Standpunktverdi::For, 'NATO er sikkerhetsgarantien vår.');

        $this->get('/tema/nato')
            ->assertOk()
            ->assertSee('«NATO er sikkerhetsgarantien vår.»')
            ->assertSee('Prinsipp- og handlingsprogram 2025–2029, s. 7')
            ->assertDontSee('handlingsdelen')
            ->assertDontSee('Prinsipielt syn:')
            ->assertDontSee('Ingen prinsipprogram');

        $this->get('/tema/nato?visning=prinsipprogram')
            ->assertOk()
            ->assertSee('«NATO er sikkerhetsgarantien vår.»')
            ->assertDontSee('Partiet har ikke et gjeldende prinsipprogram.');
    }

    public function test_upubliserte_temaer_finnes_ikke(): void
    {
        Tema::create(['navn' => 'EU', 'slug' => 'eu', 'pastand' => 'Norge bør bli med i EU.', 'status' => 'analysert']);

        $this->get('/tema/eu')->assertNotFound();
        $this->get('/tema/finnes-ikke')->assertNotFound();
    }

    public function test_sok_pa_navn_eller_synonym_gar_rett_til_temaet(): void
    {
        $this->get('/sok?q=Forsvarsallianse')->assertRedirect(route('tema', ['tema' => $this->nato, 'sok' => 'Forsvarsallianse']));
        $this->get('/sok?q=nato')->assertRedirect(route('tema', ['tema' => $this->nato, 'sok' => 'nato']));
        $this->get('/sok?q=')->assertRedirect(route('forside'));

        // Temasiden sporer søket i stedet for en sidevisning.
        $this->get('/tema/nato?sok=forsvarsallianse')
            ->assertSee("_paq.push(['trackSiteSearch', 'forsvarsallianse', 'Tema', 1]);", false);
    }

    public function test_sok_uten_treff_foreslar_naermeste_tema_og_lar_brukeren_foresla_det(): void
    {
        Tema::create(['navn' => 'Eiendomsskatt', 'slug' => 'eiendomsskatt', 'pastand' => 'Kommunene bør kunne kreve eiendomsskatt.', 'status' => 'publisert']);

        $this->get('/sok?q=eiendomskatt')
            ->assertOk()
            ->assertSee('Vi har ikke analysert «eiendomskatt» ennå')
            ->assertSee('Mente du')
            ->assertSee('href="'.route('tema', ['tema' => 'eiendomsskatt', 'sok' => 'eiendomskatt']).'"', false)
            ->assertSee('Foreslå «eiendomskatt» som tema')
            ->assertSee("_paq.push(['trackSiteSearch', 'eiendomskatt', 'Tema', 0]);", false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get('/sok?q=bompenger')->assertSee('Vi har ikke analysert «bompenger» ennå')->assertDontSee('Mente du');
    }

    public function test_sok_med_flere_treff_viser_liste(): void
    {
        Tema::create(['navn' => 'Formuesskatt', 'slug' => 'formuesskatt', 'pastand' => 'Formuesskatten bør fjernes.', 'status' => 'publisert']);
        Tema::create(['navn' => 'Eiendomsskatt', 'slug' => 'eiendomsskatt', 'pastand' => 'Kommunene bør kunne kreve eiendomsskatt.', 'status' => 'publisert']);

        $this->get('/sok?q=skatt')
            ->assertOk()
            ->assertSee('2 temaer passer med søket.')
            ->assertSee('Formuesskatt')
            ->assertSee('Eiendomsskatt');
    }

    public function test_foresla_tema_lagres_og_telles_opp(): void
    {
        $this->from('/sok?q=bompenger')->post('/tema/foresla', ['tema' => 'Bompenger'])
            ->assertRedirect('/sok?q=bompenger')
            ->assertSessionHas('status');
        $this->post('/tema/foresla', ['tema' => ' bompenger ']);
        $this->post('/tema/foresla', ['tema' => 'Bompenger', 'nettside' => 'spam'])->assertSessionHasErrors('nettside');

        $this->assertSame(1, Temaforslag::count());
        $this->assertSame(2, Temaforslag::first()->antall);
        $this->assertSame('Bompenger', Temaforslag::first()->sokeord);
    }

    public function test_meld_fra_om_feil_lagres_med_standpunktet(): void
    {
        $h = $this->parti('h', 'Høyre');
        $standpunkt = $this->standpunkt($this->program($h, 'arbeidsprogram', 'Norge skal være med i NATO.'), Standpunktverdi::For, 'Norge skal være med i NATO.');

        $this->post('/tema/nato/meld-fra', ['parti_id' => $h->id, 'standpunkt_id' => $standpunkt->id, 'tekst' => 'Sitatet er tatt ut av sammenheng.'])
            ->assertRedirect(route('tema', $this->nato).'#parti-'.$h->id);

        $melding = Tilbakemelding::first();
        $this->assertSame($standpunkt->id, $melding->standpunkt_id);
        $this->assertSame($this->nato->id, $melding->tema_id);
        $this->assertSame('Sitatet er tatt ut av sammenheng.', $melding->tekst);
    }

    public function test_programsiden_har_faste_ider_for_avsnitt_og_setninger(): void
    {
        $h = $this->parti('h', 'Høyre');
        $this->program($h, 'arbeidsprogram', 'Norge skal være med i NATO.');

        $this->get('/program/h/arbeidsprogram-2025')
            ->assertOk()
            ->assertSee('href="'.route('program.kapittel', [$h, 'arbeidsprogram-2025', 'kap-2']).'"', false);

        $this->get('/program/h/arbeidsprogram-2025/kap-2')
            ->assertOk()
            ->assertSee('<div class="avsnitt" id="a2">', false)
            ->assertSee('<span class="setning" id="a2-s1">Norge skal være med i NATO.</span>', false)
            ->assertSee('<span class="setning" id="a2-s2">Forsvaret skal styrkes.</span>', false)
            ->assertSee('href="https://h.no/program.pdf#page=7"', false)
            ->assertSee('rel="prev"', false);

        $this->get('/program/h/arbeidsprogram-2025/kap-9')->assertNotFound();
        $this->get('/program')->assertOk()->assertSee('Arbeidsprogram 2025–2029');
    }

    public function test_programmer_som_ikke_er_godkjent_vises_ikke(): void
    {
        $h = $this->parti('h', 'Høyre');
        $this->program($h, 'arbeidsprogram', 'Norge skal være med i NATO.', status: 'digitalisert');

        $this->get('/program/h/arbeidsprogram-2025')->assertNotFound();
        $this->get('/program/h/arbeidsprogram-2025/kap-2')->assertNotFound();
    }

    public function test_sitemap_og_llms_txt_har_temaene_og_programmene(): void
    {
        $h = $this->parti('h', 'Høyre');
        $this->program($h, 'arbeidsprogram', 'Norge skal være med i NATO.');

        $xml = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString('<loc>'.route('tema', $this->nato).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('program', [$h, 'arbeidsprogram-2025']).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('program.kapittel', [$h, 'arbeidsprogram-2025', 'kap-2']).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('om').'</loc>', $xml);
        $this->assertStringNotContainsString('/sok', $xml);
        $this->assertStringNotContainsString('foresla', $xml);

        $this->standpunkt(Program::first(), Standpunktverdi::For, 'Norge skal være med i NATO.');
        $this->get('/llms-full.txt')->assertSee('Kilde: [Arbeidsprogram 2025–2029, s. 7]('.url('/program/h/arbeidsprogram-2025/kap-2#a2-s1').')', false);

        $this->get('/llms.txt')->assertSee('- [Hva mener partiene om NATO?]('.route('tema', $this->nato).')', false);
    }

    public function test_forslag_og_tilbakemeldinger_vises_i_admin(): void
    {
        $h = $this->parti('h', 'Høyre');
        Temaforslag::registrer('Bompenger');
        Tilbakemelding::create(['tema_id' => $this->nato->id, 'parti_id' => $h->id, 'tekst' => 'Feil merkelapp']);
        $admin = User::factory()->create(['er_admin' => true]);

        $this->actingAs($admin)->get('/admin/temaforslag')->assertOk()->assertSee('Bompenger');
        $this->actingAs($admin)->get('/admin/tilbakemeldinger')->assertOk()->assertSee('Feil merkelapp');
    }

    public function test_om_siden_forklarer_metoden(): void
    {
        $this->get('/om')->assertOk()->assertSee('Om og metode')->assertSee('Delt / uklart');
    }

    public function test_sidene_krediterer_sikt_for_partidokumentene(): void
    {
        $sitat = 'Sikt – Kunnskapssektorens tjenesteleverandør. (u.å.).';

        $this->get('/om')->assertOk()->assertSee($sitat, false)->assertSee('https://polsys.sikt.no/', false);
        $this->get('/')->assertOk()->assertSee($sitat, false)->assertSee('Hentet 25. oktober 2026', false);
    }

    private function tema(string $slug, string $navn): Tema
    {
        return Tema::create(['navn' => $navn, 'slug' => $slug, 'pastand' => "Påstand om {$navn}.", 'status' => 'publisert']);
    }

    public function test_temasiden_viser_svaret_forst_gruppert_etter_merkelapp(): void
    {
        $h = $this->parti('h', 'Høyre');
        $r = $this->parti('r', 'Rødt');
        $this->parti('v', 'Venstre');
        $this->standpunkt($this->program($h, 'arbeidsprogram', 'Norge skal være med i NATO.'), Standpunktverdi::For, 'Norge skal være med i NATO.');
        $this->standpunkt($this->program($r, 'arbeidsprogram', 'Norge skal ut av NATO.'), Standpunktverdi::Mot, 'Norge skal ut av NATO.');

        $html = $this->get('/tema/nato')->assertOk()
            ->assertSee('aria-label="Kort svar"', false)
            ->assertSee('href="#parti-'.$h->id.'"', false)
            ->assertSee('1 av 3')
            ->assertSee('Ikke kontrollert ennå')
            ->assertSee('<h3 class="gruppetittel">For</h3>', false)
            ->assertSee('<h3 class="gruppetittel">Mot</h3>', false)
            ->assertSee('<details class="partirad" id="parti-'.$h->id.'"', false)
            ->assertSee('href="'.route('om').'#skalaen"', false)
            ->getContent();

        // Svaret står før sitatene, og velgeren for program står etter.
        $this->assertLessThan(strpos($html, 'Hva partiene skriver'), strpos($html, 'Kort svar'));
        $this->assertLessThan(strpos($html, 'Vis standpunkt fra:'), strpos($html, 'Hva partiene skriver'));
    }

    public function test_lange_sitater_kortes_ned_og_like_setninger_siteres_en_gang(): void
    {
        $h = $this->parti('h', 'Høyre');
        $lang = 'Norge skal være et aktivt og troverdig medlem av NATO, styrke forsvaret i nord, bidra mer til alliansens felles operasjoner og samtidig bygge et tettere nordisk forsvarssamarbeid med Sverige, Finland og Danmark.';
        $program = $this->program($h, 'arbeidsprogram', $lang);
        $standpunkt = $this->standpunkt($program, Standpunktverdi::For, $lang);
        $standpunkt->kildesetninger()->attach(Setning::where('program_id', $program->id)->where('tekst', 'Forsvaret skal styrkes.')->value('id'), ['rekkefolge' => 2]);
        // Samme tekst to ganger blir bare sitert én gang.
        $kopi = Setning::where('program_id', $program->id)->where('tekst', $lang)->first()->replicate();
        $kopi->nokkel .= '-kopi';
        $kopi->save();
        $standpunkt->kildesetninger()->attach($kopi->id, ['rekkefolge' => 3]);

        $kort = Kildeutdrag::fra($standpunkt->fresh());
        $this->assertSame($lang.' Forsvaret skal styrkes.', $kort->utdrag);
        $this->assertLessThanOrEqual(162, mb_strlen($kort->kort()));
        $this->assertStringEndsWith(' …', $kort->kort());
        $this->assertStringStartsWith('Norge skal være et aktivt', $kort->kort());

        $this->get('/tema/nato')->assertSee('«'.$kort->kort().'»')->assertSee('«'.$lang.' Forsvaret skal styrkes.»');
    }

    public function test_forsiden_viser_de_mest_besokte_temaene_og_tre_tilfeldige(): void
    {
        $h = $this->parti('h', 'Høyre');
        $program = $this->program($h, 'arbeidsprogram', 'Norge skal være med i NATO.');
        $temaer = collect(range(1, 9))->map(fn (int $i) => $this->tema("tema-{$i}", "Tema {$i}"));
        $this->standpunkt($program, Standpunktverdi::For, 'Norge skal være med i NATO.');

        foreach ([[$temaer[0], 9], [$temaer[1], 7], [$temaer[2], 5], [$this->nato, 3], [$temaer[3], 1]] as [$tema, $antall]) {
            Temabesok::create(['tema_id' => $tema->id, 'dato' => now()->toDateString(), 'antall' => $antall]);
        }

        $html = $this->get('/')->assertOk()
            ->assertSee('De mest besøkte temaene og noen tilfeldig valgte.')
            ->assertSee('href="'.route('tema', $this->nato).'#parti-'.$h->id.'"', false)
            ->assertSee('Se alle 10 temaene')
            ->assertDontSee('Slik leser du svaret')
            ->getContent();

        $this->assertSame(4, substr_count($html, 'data-gruppe="populær"'));
        $this->assertSame(6, substr_count($html, 'data-gruppe="tilfeldig"'));
        // Tre tilfeldige vises uten JavaScript, resten er skjult.
        $this->assertSame(7, preg_match_all('/<tr\s+data-gruppe="[^"]+"\s*>/u', $html));
        // De mest besøkte står først, i rekkefølge.
        $this->assertLessThan(strpos($html, 'Tema 2</a>'), strpos($html, 'Tema 1</a>'));
        $this->assertLessThan(strpos($html, 'NATO</a>'), strpos($html, 'Tema 3</a>'));
    }

    public function test_besok_pa_temasiden_telles_men_ikke_fra_roboter(): void
    {
        $this->get('/tema/nato', ['User-Agent' => 'Mozilla/5.0 (Macintosh)'])->assertOk();
        $this->get('/tema/nato', ['User-Agent' => 'Mozilla/5.0 (Macintosh)'])->assertOk();
        $this->get('/tema/nato', ['User-Agent' => 'Googlebot/2.1'])->assertOk();
        $this->get('/tema/nato', ['User-Agent' => 'Mozilla/5.0', 'Sec-Purpose' => 'prefetch'])->assertOk();
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (Macintosh)'])->assertOk();

        $this->assertSame(2, (int) Temabesok::where('tema_id', $this->nato->id)->sum('antall'));
        $this->assertSame([$this->nato->id], Temabesok::mestBesokte()->all());
    }

    public function test_alle_temaer_vises_i_en_tabell_med_partivelger(): void
    {
        $h = $this->parti('h', 'Høyre');
        $this->parti('r', 'Rødt');
        $this->standpunkt($this->program($h, 'arbeidsprogram', 'Norge skal være med i NATO.'), Standpunktverdi::For, 'Norge skal være med i NATO.');

        $this->get('/tema')->assertOk()
            ->assertSee('data-partivelger', false)
            ->assertSee('<input type="checkbox" value="r" data-parti-valg>', false)
            ->assertSee('data-verdi="for"', false)
            ->assertSee('data-verdi="ikke-vurdert"', false)
            ->assertSee('Høyre: For');
    }

    public function test_partisiden_viser_alt_partiet_mener(): void
    {
        $h = $this->parti('h', 'Høyre');
        $program = $this->program($h, 'arbeidsprogram', 'Norge skal være med i NATO.');
        $this->standpunkt($program, Standpunktverdi::For, 'Norge skal være med i NATO.');
        $skatt = $this->tema('formuesskatt', 'Formuesskatt');
        $this->standpunkt($program, Standpunktverdi::IkkeOmtalt, 'Vi vil ha lav skatt.', tema: $skatt);
        $this->tema('eu', 'EU');

        $this->get('/parti')->assertOk()->assertSee('href="'.route('parti', $h).'"', false);

        $this->get('/parti/h')->assertOk()
            ->assertSee('Hva mener Høyre?')
            ->assertSee('href="'.route('tema', $this->nato).'#parti-'.$h->id.'"', false)
            ->assertSee('«Norge skal være med i NATO.»')
            ->assertSee('Ikke omtalt')
            ->assertSee('1 tema er ikke ferdig kontrollert for partiet ennå.');

        $h->update(['aktiv_til' => now()->subYear()]);
        $this->get('/parti/h')->assertNotFound();
        $this->get('/parti/finnes-ikke')->assertNotFound();

        $this->assertStringContainsString('<loc>'.route('partier').'</loc>', $this->get('/sitemap.xml')->getContent());
    }
}
