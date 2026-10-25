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
use App\Enums\Standpunktstatus;
use App\Feilsoking\Feilmerker;
use App\Feilsoking\Sensurerer;
use App\Filament\Resources\Feilmerkinger\Pages\ListFeilmerkinger;
use App\Filament\Resources\Standpunkter\Pages\ListStandpunkter;
use App\Filament\Resources\Standpunkter\Pages\VisStandpunkt;
use App\Models\Feilmerking;
use App\Models\Parti;
use App\Models\Program;
use App\Models\Standpunkt;
use App\Models\Tema;
use App\Models\Tilbakemelding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FalskOllama;
use Tests\TestCase;

class FeilmerkingTest extends TestCase
{
    use RefreshDatabase;

    private Standpunkt $standpunkt;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hvamenerpartiet.analyse.kjoringer' => 2,
            'hvamenerpartiet.analyse.terskel' => 0.3,
        ]);

        $ollama = FalskOllama::start();

        $parti = Parti::create(['navn' => 'Testpartiet', 'forkortelse' => 'TP', 'slug' => 'tp']);
        $program = Program::create([
            'parti_id' => $parti->id, 'type' => 'arbeidsprogram', 'tittel' => 'tp-ap2025', 'slug' => 'tp-ap2025', 'nokkel' => 'tp-ap2025',
            'periode' => '2025-2029', 'digitalisering_status' => Digitaliseringsstatus::Godkjent,
        ]);
        app(ProgramLagrer::class)->lagre($program, new Dokument([
            new DokumentKapittel('Forsvar', 1, 3, [
                new DokumentAvsnitt('Norge skal være et aktivt medlem av NATO. Forsvarsalliansen er grunnmuren i vår sikkerhet.', 3, 3),
            ]),
        ]));
        app(Tekstbitbygger::class)->bygg($program);
        app(Embedder::class)->forProgram($program);

        $tema = Tema::create([
            'navn' => 'NATO', 'slug' => 'nato', 'pastand' => 'Norge bør være medlem av NATO.',
            'synonymer' => ['NATO'], 'status' => 'godkjent',
        ]);
        app(Embedder::class)->forTema($tema);

        $svar = ['standpunkt' => 'for', 'sikkerhet' => 'høy', 'kildesetninger' => ['S1'], 'begrunnelse' => 'Begrunnelse.'];
        $ollama->chatsvar = [$svar, $svar];
        $this->standpunkt = app(Klassifiserer::class)->klassifiser($tema, $program, Kildetype::Arbeidsprogram);
    }

    public function test_merking_tar_med_vurdering_kilder_prompt_og_modellsvar(): void
    {
        $feil = app(Feilmerker::class)->merk($this->standpunkt, 'Burde vært delvis for.');
        $p = $feil->pakke;

        $this->assertSame('Burde vært delvis for.', $feil->begrunnelse);
        $this->assertSame('standpunkt', $p['type']);
        $this->assertSame('for', $p['standpunkt']['verdi']);
        $this->assertSame('Norge skal være et aktivt medlem av NATO.', $p['kildesetninger'][0]['tekst']);
        $this->assertCount(2, $p['modellsvar']);
        $this->assertSame(Klassifiserer::PROMPT_VERSJON, $p['prompt']['gjeldende_versjon']);
        $this->assertStringContainsString('[S1] Norge skal være et aktivt medlem av NATO.', $p['prompt']['meldinger'][1]['content']);
    }

    public function test_hemmeligheter_og_e_post_kommer_aldri_med(): void
    {
        config(['app.key' => 'base64:superhemmeligappnokkel123456']);

        $feil = app(Feilmerker::class)->merk($this->standpunkt, 'Se base64:superhemmeligappnokkel123456 og lars@example.com, token ghp_abcdefghijklmnopqrstuvwxyz123456');
        $json = json_encode(app(Feilmerker::class)->eksport(Feilmerking::all()));

        $this->assertStringNotContainsString('superhemmeligappnokkel', $json);
        $this->assertStringNotContainsString('lars@example.com', $json);
        $this->assertStringNotContainsString('ghp_abcdefghijklmnopqrstuvwxyz123456', $json);
        $this->assertStringContainsString(Sensurerer::SKJULT, $feil->begrunnelse);
    }

    public function test_tilbakemelding_tar_med_teksten_og_standpunktet(): void
    {
        $tilbakemelding = Tilbakemelding::create([
            'tema_id' => $this->standpunkt->tema_id, 'parti_id' => $this->standpunkt->program->parti_id,
            'standpunkt_id' => $this->standpunkt->id, 'tekst' => 'Sitatet er tatt ut av sammenheng',
        ]);

        $p = app(Feilmerker::class)->merk($tilbakemelding, 'Den besøkende har rett')->pakke;

        $this->assertSame('tilbakemelding', $p['type']);
        $this->assertSame('Sitatet er tatt ut av sammenheng', $p['tilbakemelding']['tekst']);
        $this->assertSame('for', $p['standpunkt']['verdi']);
    }

    public function test_eksporten_har_alle_apne_feil_og_merker_dem_som_eksportert(): void
    {
        $merker = app(Feilmerker::class);
        $forste = $merker->merk($this->standpunkt, 'Første');
        $lost = $merker->merk($this->standpunkt, 'Løst');
        $lost->update(['lost' => true]);

        $eksport = $merker->eksport(Feilmerking::query()->apne()->get());

        $this->assertSame(1, $eksport['antall']);
        $this->assertSame('Første', $eksport['feil'][0]['begrunnelse']);
        $this->assertSame('for', $eksport['feil'][0]['standpunkt']['verdi']);
        $this->assertNotNull($forste->refresh()->eksportert);
        $this->assertNull($lost->refresh()->eksportert);
    }

    public function test_handlingene_i_admin_merker_og_eksporterer(): void
    {
        $admin = User::factory()->create(['er_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(ListStandpunkter::class)
            ->callTableAction('merkSomFeil', $this->standpunkt, ['begrunnelse' => 'Sjekk kildene'])
            ->assertHasNoTableActionErrors();

        $feil = Feilmerking::sole();
        $this->assertSame('Sjekk kildene', $feil->begrunnelse);
        $this->assertSame($admin->id, $feil->merket_av);

        Livewire::test(ListFeilmerkinger::class)
            ->callAction('eksporter', ['bare_nye' => false, 'med_loste' => false])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded();

        $this->assertNotNull($feil->refresh()->eksportert);
    }

    public function test_begrunnelse_er_pakrevd(): void
    {
        $this->actingAs(User::factory()->create(['er_admin' => true]));

        Livewire::test(ListStandpunkter::class)
            ->callTableAction('merkSomFeil', $this->standpunkt, ['begrunnelse' => ''])
            ->assertHasTableActionErrors(['begrunnelse' => 'required']);

        $this->assertDatabaseCount('feilmerkinger', 0);
    }

    public function test_admin_viser_feilene_og_knappen_pa_standpunktet(): void
    {
        app(Feilmerker::class)->merk($this->standpunkt, 'Feil kilde');
        $this->actingAs(User::factory()->create(['er_admin' => true]));

        $this->get('/admin/feil')->assertOk()->assertSee('Feil kilde')->assertSee('Eksporter feil');
        $this->get('/admin/standpunkter/'.$this->standpunkt->id)->assertOk()->assertSee('Merk som feil');
    }

    public function test_standpunktsiden_har_godkjenn_korriger_og_avvis(): void
    {
        $admin = User::factory()->create(['er_admin' => true]);
        $this->actingAs($admin);

        $this->get('/admin/standpunkter/'.$this->standpunkt->id)
            ->assertOk()->assertSee('Godkjenn')->assertSee('Korriger standpunkt')->assertSee('Avvis');

        Livewire::test(VisStandpunkt::class, ['record' => $this->standpunkt->id])
            ->callAction('godkjenn')
            ->assertHasNoActionErrors()
            ->assertActionHidden('godkjenn');

        $this->standpunkt->refresh();
        $this->assertSame(Standpunktstatus::Godkjent, $this->standpunkt->status);
        $this->assertSame($admin->id, $this->standpunkt->godkjent_av);

        Livewire::test(VisStandpunkt::class, ['record' => $this->standpunkt->id])
            ->callAction('korriger', ['verdi' => 'delvis_for'])
            ->assertHasNoActionErrors();

        $this->assertSame(Standpunktstatus::Korrigert, $this->standpunkt->refresh()->status);
        $this->assertSame('delvis_for', $this->standpunkt->verdi->value);
    }
}
