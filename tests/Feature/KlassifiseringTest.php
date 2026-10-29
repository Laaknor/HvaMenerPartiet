<?php

namespace Tests\Feature;

use App\Analyse\Embedder;
use App\Analyse\Klassifiserer;
use App\Analyse\Tekstbitbygger;
use App\Digitalisering\Dokument;
use App\Digitalisering\DokumentAvsnitt;
use App\Digitalisering\DokumentKapittel;
use App\Digitalisering\ProgramLagrer;
use App\Enums\Digitaliseringsstatus;
use App\Enums\Kildetype;
use App\Enums\Programdel;
use App\Enums\Programdeling;
use App\Enums\Sikkerhet;
use App\Enums\Standpunktstatus;
use App\Enums\Standpunktverdi;
use App\Models\Parti;
use App\Models\Program;
use App\Models\Tema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\FalskOllama;
use Tests\TestCase;

class KlassifiseringTest extends TestCase
{
    use RefreshDatabase;

    private FalskOllama $ollama;

    private Program $program;

    private Tema $nato;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hvamenerpartiet.analyse.kjoringer' => 2, 'hvamenerpartiet.analyse.terskel' => 0.3]);
        $this->ollama = FalskOllama::start();

        $parti = Parti::create(['navn' => 'Testpartiet', 'forkortelse' => 'TP', 'slug' => 'tp']);
        $this->program = $this->lagProgram($parti, 'arbeidsprogram', 'tp-ap2025', [
            new DokumentKapittel('Forsvar', 1, 3, [
                new DokumentAvsnitt('Norge skal være et aktivt medlem av NATO. Forsvarsalliansen er grunnmuren i vår sikkerhet.', 3, 3),
                new DokumentAvsnitt("Testpartiet vil:\n• Øke forsvarsbudsjettet.\n• Styrke Heimevernet.", 3, 3),
            ]),
            new DokumentKapittel('Landbruk', 1, 9, [
                new DokumentAvsnitt('Bøndene skal ha inntekt på linje med andre grupper. Matjorda skal vernes.', 9, 9),
            ]),
        ]);

        $this->nato = Tema::create([
            'navn' => 'NATO', 'slug' => 'nato', 'pastand' => 'Norge bør være medlem av NATO.',
            'synonymer' => ['NATO', 'forsvarsallianse'], 'status' => 'godkjent',
        ]);
        app(Embedder::class)->forTema($this->nato);
    }

    /** @param list<DokumentKapittel> $kapitler */
    private function lagProgram(Parti $parti, string $type, string $nokkel, array $kapitler): Program
    {
        $program = Program::create([
            'parti_id' => $parti->id, 'type' => $type, 'tittel' => $nokkel, 'slug' => $nokkel, 'nokkel' => $nokkel,
            'periode' => '2025-2029', 'digitalisering_status' => Digitaliseringsstatus::Godkjent,
        ]);
        app(ProgramLagrer::class)->lagre($program, new Dokument($kapitler));
        app(Tekstbitbygger::class)->bygg($program);
        app(Embedder::class)->forProgram($program);

        return $program;
    }

    private function svar(string $verdi, array $kilder, string $sikkerhet = 'høy'): array
    {
        return ['standpunkt' => $verdi, 'sikkerhet' => $sikkerhet, 'kildesetninger' => $kilder, 'begrunnelse' => 'Begrunnelse.'];
    }

    public function test_setter_standpunkt_med_ordrett_utdrag_fra_kildesetningene(): void
    {
        $this->ollama->chatsvar = [
            $this->svar('for', ['tp-ap2025-k1-a1-s2', 'tp-ap2025-k1-a1-s1']),
            $this->svar('for', ['tp-ap2025-k1-a1-s1'], 'middels'),
        ];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::For, $s->verdi);
        $this->assertSame(Sikkerhet::Middels, $s->sikkerhet, 'Laveste sikkerhet av kjøringene brukes');
        $this->assertFalse($s->uenighet);
        $this->assertSame(Standpunktstatus::Maskinell, $s->status);
        $this->assertSame(Klassifiserer::PROMPT_VERSJON, $s->prompt_versjon);
        // Utdraget kommer fra databasen, i dokumentrekkefølge.
        $this->assertSame('Norge skal være et aktivt medlem av NATO. Forsvarsalliansen er grunnmuren i vår sikkerhet.', $s->utdrag());
        $this->assertCount(2, $this->ollama->chatkall);
    }

    public function test_modellen_far_setnings_id_er_men_ikke_partinavnet(): void
    {
        $this->ollama->chatsvar = [$this->svar('for', ['tp-ap2025-k1-a1-s1']), $this->svar('for', ['tp-ap2025-k1-a1-s1'])];

        app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $melding = $this->ollama->sisteBrukermelding();
        $this->assertStringContainsString('[S1] Norge skal være et aktivt medlem av NATO.', $melding);
        $this->assertStringContainsString('Påstand: «Norge bør være medlem av NATO.»', $melding);
        $this->assertStringNotContainsString('Testpartiet', $melding);
        $this->assertStringContainsString('Partiet vil:', $melding);
        $this->assertSame(Klassifiserer::svarskjema(), $this->ollama->chatkall[0]['format']);
    }

    public function test_ukjente_setnings_id_er_forkastes(): void
    {
        $this->ollama->chatsvar = [
            $this->svar('for', ['tp-ap2025-k1-a1-s1', 'oppdiktet-id', 'tp-ap2025-k2-a1-s1-finnes-ikke']),
            $this->svar('for', ['tp-ap2025-k1-a1-s1']),
        ];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(['tp-ap2025-k1-a1-s1'], $s->kildesetninger->pluck('nokkel')->all());
        $this->assertSame(['oppdiktet-id', 'tp-ap2025-k2-a1-s1-finnes-ikke'], $s->kjoringer[0]['ugyldige_kilder']);
    }

    public function test_uenighet_mellom_kjoringer_gir_delt_uklart(): void
    {
        $this->ollama->chatsvar = [$this->svar('for', ['tp-ap2025-k1-a1-s1']), $this->svar('mot', ['tp-ap2025-k1-a1-s2'])];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::DeltUklart, $s->verdi);
        $this->assertSame(Sikkerhet::Lav, $s->sikkerhet);
        $this->assertTrue($s->uenighet);
        $this->assertCount(2, $s->kildesetninger);
    }

    public function test_standpunkt_uten_gyldige_kilder_godtas_ikke(): void
    {
        // To kjøringer, hver med ett nytt forsøk.
        $this->ollama->chatsvar = [$this->svar('for', []), $this->svar('for', []), $this->svar('for', ['finnes-ikke']), $this->svar('for', ['S99'])];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::DeltUklart, $s->verdi);
        $this->assertTrue($s->uenighet);
        $this->assertCount(0, $s->kildesetninger);
    }

    public function test_ugyldig_json_regnes_som_ugyldig_kjoring(): void
    {
        $this->ollama->chatsvar = ['dette er ikke json', $this->svar('mot', ['tp-ap2025-k1-a1-s1'])];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::Mot, $s->verdi);
        $this->assertStringContainsString('1 av 2 kjøringer', $s->merknader[0]);
    }

    public function test_ingen_treff_gir_ikke_omtalt_uten_llm_kall(): void
    {
        $tema = Tema::create(['navn' => 'Atomkraft', 'slug' => 'atomkraft', 'pastand' => 'Norge bør bygge atomkraftverk.', 'synonymer' => ['kjernekraft']]);
        config(['hvamenerpartiet.analyse.terskel' => 0.99]);

        app(Embedder::class)->forTema($tema);
        $s = app(Klassifiserer::class)->klassifiser($tema, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::IkkeOmtalt, $s->verdi);
        $this->assertNull($s->modell);
        $this->assertCount(0, $this->ollama->chatkall);
    }

    public function test_nokkelord_gir_treff_selv_under_terskelen(): void
    {
        config(['hvamenerpartiet.analyse.terskel' => 0.99]);
        $this->ollama->chatsvar = [$this->svar('for', ['tp-ap2025-k1-a1-s1']), $this->svar('for', ['tp-ap2025-k1-a1-s1'])];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::For, $s->verdi);
        $this->assertStringNotContainsString('Bøndene', $this->ollama->sisteBrukermelding());
    }

    public function test_ny_klassifisering_som_endrer_noe_sendes_tilbake_til_godkjenning(): void
    {
        $this->ollama->chatsvar = [$this->svar('for', ['tp-ap2025-k1-a1-s1']), $this->svar('for', ['tp-ap2025-k1-a1-s1'])];
        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);
        $s->update(['status' => Standpunktstatus::Godkjent, 'godkjent_tid' => now()]);

        // Samme svar: godkjenningen beholdes.
        $this->ollama->chatsvar = [$this->svar('for', ['tp-ap2025-k1-a1-s1']), $this->svar('for', ['tp-ap2025-k1-a1-s1'])];
        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);
        $this->assertSame(Standpunktstatus::Godkjent, $s->status);

        // Nytt svar: tilbake til godkjenning.
        $this->ollama->chatsvar = [$this->svar('delvis_for', ['tp-ap2025-k1-a1-s1']), $this->svar('delvis_for', ['tp-ap2025-k1-a1-s1'])];
        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);
        $this->assertSame(Standpunktstatus::Maskinell, $s->status);
        $this->assertNull($s->godkjent_tid);
    }

    public function test_kombinert_dokument_klassifiseres_per_del(): void
    {
        $parti = Parti::create(['navn' => 'Kombipartiet', 'forkortelse' => 'KP', 'slug' => 'kp']);
        $program = $this->lagProgram($parti, 'kombinert', 'kp-kp2025', [
            new DokumentKapittel('Prinsipper', 1, 2, [new DokumentAvsnitt('Vi mener Norge på sikt bør stå utenfor NATO.', 2, 2)], Programdel::Prinsipp),
            new DokumentKapittel('Handlingsprogram', 1, 5, [new DokumentAvsnitt('I perioden vil vi ikke arbeide for utmelding av NATO.', 5, 5)], Programdel::Handling),
        ]);

        $this->ollama->chatsvar = [
            $this->svar('mot', ['kp-kp2025-k1-a1-s1']), $this->svar('mot', ['kp-kp2025-k1-a1-s1']),
            fn () => $this->svar('delvis_for', ['kp-kp2025-k2-a1-s1']), $this->svar('delvis_for', ['kp-kp2025-k2-a1-s1']),
        ];

        $prinsipp = app(Klassifiserer::class)->klassifiser($this->nato, $program, Kildetype::Prinsipprogram);
        $this->assertStringContainsString('prinsipprogram', $this->ollama->sisteBrukermelding());
        $this->assertStringNotContainsString('I perioden vil vi', $this->ollama->sisteBrukermelding());

        $handling = app(Klassifiserer::class)->klassifiser($this->nato, $program, Kildetype::Arbeidsprogram);
        $this->assertStringNotContainsString('på sikt bør stå utenfor', $this->ollama->sisteBrukermelding());

        $this->assertSame(Standpunktverdi::Mot, $prinsipp->verdi);
        $this->assertSame(Standpunktverdi::DelvisFor, $handling->verdi);
        $this->assertSame(2, $program->standpunkter()->count());
    }

    public function test_kombinert_dokument_uten_prinsippdel_leses_samlet(): void
    {
        $parti = Parti::create(['navn' => 'Samlepartiet', 'forkortelse' => 'SP', 'slug' => 'sa']);
        $program = $this->lagProgram($parti, 'kombinert', 'sa-kp2025', [
            new DokumentKapittel('Verdier', 1, 2, [new DokumentAvsnitt('Vi mener NATO er sikkerhetsgarantien vår.', 2, 2)]),
            new DokumentKapittel('Forsvar', 1, 5, [new DokumentAvsnitt('Vi vil styrke Heimevernet.', 5, 5)], Programdel::Handling),
        ]);

        $this->assertFalse($program->erDelt());
        $this->assertSame([Kildetype::Arbeidsprogram], $program->kildetyper());

        $this->ollama->chatsvar = [$this->svar('for', ['S1']), $this->svar('for', ['S1'])];
        $s = app(Klassifiserer::class)->klassifiser($this->nato, $program, Kildetype::Arbeidsprogram);

        $this->assertStringContainsString('sikkerhetsgarantien', $this->ollama->sisteBrukermelding());
        $this->assertSame(Standpunktverdi::For, $s->verdi);

        // Delingen kan overstyres i admin.
        $program->update(['deling' => Programdeling::Delt]);
        $this->assertTrue($program->erDelt());
        $this->assertSame([Kildetype::Arbeidsprogram, Kildetype::Prinsipprogram], $program->kildetyper());
    }

    public function test_kombinert_dokument_med_prinsippdel_deles_automatisk_og_kan_samles(): void
    {
        $parti = Parti::create(['navn' => 'Kombipartiet', 'forkortelse' => 'KP', 'slug' => 'kp']);
        $program = $this->lagProgram($parti, 'kombinert', 'kp-kp2025', [
            new DokumentKapittel('Prinsipper', 1, 2, [new DokumentAvsnitt('Vi mener NATO er viktig.', 2, 2)], Programdel::Prinsipp),
        ]);

        $this->assertTrue($program->erDelt());

        $program->update(['deling' => Programdeling::Samlet]);
        $this->assertFalse($program->erDelt());
        $this->assertSame([Kildetype::Arbeidsprogram], $program->kildetyper());
    }

    public function test_nettverksfeil_kastes_sa_jobben_provers_igjen(): void
    {
        Http::fake(['*/api/chat' => fn () => throw new ConnectionException('Connection refused')]);

        $this->expectException(ConnectionException::class);
        app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);
    }

    public function test_korte_id_er_i_ulike_former_godtas(): void
    {
        $this->ollama->chatsvar = [$this->svar('for', ['S1', '[S2]']), $this->svar('for', ['s1', 2, 'tp-ap2025-k1-a1-s2'])];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::For, $s->verdi);
        $this->assertFalse($s->uenighet);
        $this->assertSame(['tp-ap2025-k1-a1-s1', 'tp-ap2025-k1-a1-s2'], $s->kildesetninger->pluck('nokkel')->all());
    }

    public function test_svar_uten_kilder_far_ett_nytt_forsok(): void
    {
        $this->ollama->chatsvar = [
            $this->svar('for', []), $this->svar('for', ['S1']),
            $this->svar('for', ['S1']),
        ];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::For, $s->verdi);
        $this->assertFalse($s->uenighet);
        $this->assertTrue($s->kjoringer[0]['nytt_forsok']);
        $this->assertCount(3, $this->ollama->chatkall);
        $this->assertStringContainsString('mangler gyldige ID-er', end($this->ollama->chatkall[1]['messages'])['content']);
    }

    public function test_tenkemodus_slas_av_og_json_med_tenkeblokk_leses(): void
    {
        $this->ollama->chatsvar = [
            "<think>Teksten sier at NATO er sikkerhetsgarantien.</think>\n```json\n".json_encode($this->svar('for', ['S1']))."\n```",
            $this->svar('for', ['S1']),
        ];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::For, $s->verdi);
        $this->assertFalse($this->ollama->chatkall[0]['think']);
    }

    private function atomprogram(): Program
    {
        $parti = Parti::create(['navn' => 'Fredspartiet', 'forkortelse' => 'FP', 'slug' => 'fp']);

        return $this->lagProgram($parti, 'arbeidsprogram', 'fp-ap2025', [
            new DokumentKapittel('Fred', 1, 2, [new DokumentAvsnitt('Verdens atomvåpenarsenaler er en trussel. Norge skal gå foran for en atomvåpenfri verden.', 2, 2)]),
            new DokumentKapittel('Energi', 1, 4, [new DokumentAvsnitt('Vi vil utrede små kjernekraftverk.', 4, 4)]),
        ]);
    }

    private function atomkraft(array $forvekslinger = ['atomvåpen']): Tema
    {
        $tema = Tema::create([
            'navn' => 'Atomkraft', 'slug' => 'atomkraft', 'pastand' => 'Norge bør åpne for å bygge atomkraftverk.',
            'synonymer' => ['atomkraft', 'kjernekraft'], 'forvekslinger' => $forvekslinger, 'status' => 'godkjent',
        ]);
        app(Embedder::class)->forTema($tema);

        return $tema;
    }

    public function test_biter_som_bare_treffer_en_forveksling_sendes_ikke_til_modellen(): void
    {
        config(['hvamenerpartiet.analyse.terskel' => 0.0]);
        $program = $this->atomprogram();
        $this->ollama->chatsvar = [$this->svar('for', ['S1']), $this->svar('for', ['S1'])];

        $s = app(Klassifiserer::class)->klassifiser($this->atomkraft(), $program, Kildetype::Arbeidsprogram);

        $melding = $this->ollama->sisteBrukermelding();
        $this->assertStringContainsString('kjernekraftverk', $melding);
        $this->assertStringNotContainsString('atomvåpenarsenaler', $melding);
        $this->assertStringContainsString('Ikke det samme som: atomvåpen.', $melding);
        $this->assertSame(Standpunktverdi::For, $s->verdi);
    }

    public function test_bit_med_bade_tema_og_forveksling_beholdes(): void
    {
        config(['hvamenerpartiet.analyse.terskel' => 0.0]);
        $parti = Parti::create(['navn' => 'Blandpartiet', 'forkortelse' => 'BP', 'slug' => 'bp']);
        $program = $this->lagProgram($parti, 'arbeidsprogram', 'bp-ap2025', [
            new DokumentKapittel('Energi', 1, 2, [new DokumentAvsnitt('Kjernekraft er trygt, og har ingenting med atomvåpen å gjøre.', 2, 2)]),
        ]);
        $this->ollama->chatsvar = [$this->svar('for', ['S1']), $this->svar('for', ['S1'])];

        app(Klassifiserer::class)->klassifiser($this->atomkraft(), $program, Kildetype::Arbeidsprogram);

        $this->assertStringContainsString('Kjernekraft er trygt', $this->ollama->sisteBrukermelding());
    }

    public function test_bare_forvekslinger_gir_ikke_omtalt_uten_llm_kall(): void
    {
        config(['hvamenerpartiet.analyse.terskel' => 0.0]);
        $parti = Parti::create(['navn' => 'Fredspartiet', 'forkortelse' => 'FP', 'slug' => 'fp']);
        $program = $this->lagProgram($parti, 'prinsipprogram', 'fp-pp2023', [
            new DokumentKapittel('Fred', 1, 2, [new DokumentAvsnitt('Norge skal fordømme atomvåpen og gå foran for en atomvåpenfri verden.', 2, 2)]),
        ]);

        $s = app(Klassifiserer::class)->klassifiser($this->atomkraft(), $program, Kildetype::Prinsipprogram);

        $this->assertSame(Standpunktverdi::IkkeOmtalt, $s->verdi);
        $this->assertCount(0, $this->ollama->chatkall);
    }

    public function test_svar_om_at_teksten_ikke_handler_om_temaet_gir_ikke_omtalt(): void
    {
        $this->ollama->chatsvar = [
            ['handler_om_temaet' => false] + $this->svar('mot', ['S1']),
            ['handler_om_temaet' => false] + $this->svar('ikke_omtalt', []),
        ];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->assertSame(Standpunktverdi::IkkeOmtalt, $s->verdi);
        $this->assertFalse($s->uenighet);
        $this->assertCount(0, $s->kildesetninger);
        $this->assertFalse($s->kjoringer[0]['handler_om_temaet']);
        $this->assertStringContainsString('Satt til «ikke_omtalt»', $s->kjoringer[0]['merknad']);
        $this->assertStringContainsString('1 av 2 kjøringer oppga standpunkt', $s->merknader[0]);
        $this->assertSame('handler_om_temaet', array_key_first(Klassifiserer::svarskjema()['properties']));
    }

    public function test_modellen_beskriver_tekstens_syn_for_den_velger_standpunkt(): void
    {
        $this->ollama->chatsvar = [
            ['tekstens_syn' => 'Teksten vil at Norge skal være med i NATO.'] + $this->svar('for', ['S1']),
            $this->svar('for', ['S1']),
        ];

        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $felter = array_keys(Klassifiserer::svarskjema()['properties']);
        $this->assertLessThan(array_search('standpunkt', $felter), array_search('tekstens_syn', $felter));
        $this->assertSame('Teksten vil at Norge skal være med i NATO.', $s->kjoringer[0]['tekstens_syn']);
        $this->assertSame('', $s->kjoringer[1]['tekstens_syn']);

        // Prompten forklarer at et alternativ som utelukker påstanden, er «mot».
        $system = $this->ollama->chatkall[0]['messages'][0]['content'];
        $this->assertStringContainsString('kan også gå imot påstanden uten å nevne den', $system);
        $this->assertStringContainsString('ligner påstanden i ordlyd, betyr ikke at den er enig', $system);
    }

    public function test_adminsiden_viser_kjoringene_med_feil(): void
    {
        $this->ollama->chatsvar = [$this->svar('for', []), $this->svar('for', []), $this->svar('mot', ['S1']), $this->svar('mot', ['S1'])];
        $s = app(Klassifiserer::class)->klassifiser($this->nato, $this->program, Kildetype::Arbeidsprogram);

        $this->actingAs(User::factory()->create(['er_admin' => true]))
            ->get("/admin/standpunkter/{$s->id}")
            ->assertOk()
            ->assertSee('Ingen gyldige kildesetninger')
            ->assertSee('Råsvar');
    }
}
