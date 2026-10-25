<?php

namespace Tests\Feature;

use App\Analyse\Embedder;
use App\Enums\Digitaliseringsstatus;
use App\Enums\Kjoringsstatus;
use App\Enums\Prosessteg;
use App\Enums\Temastatus;
use App\Enums\Utlosning;
use App\Filament\Resources\Programmer\Pages\VisProgram;
use App\Flyt\Programmappe;
use App\Flyt\Prosessflyt;
use App\Flyt\Prosessplanlegger;
use App\Jobs\DigitaliserProgram;
use App\Jobs\LagEmbeddings;
use App\Jobs\LesInnProgram;
use App\Models\Parti;
use App\Models\Program;
use App\Models\Prosesskjoring;
use App\Models\Standpunkt;
use App\Models\Tema;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\FalskOllama;
use Tests\TestCase;

class ProsessflytTest extends TestCase
{
    use RefreshDatabase;

    private string $mappe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mappe = sys_get_temp_dir().'/hvamenerpartiet-docs-'.uniqid();
        mkdir($this->mappe.'/Stortingsprogram', 0777, true);
        config(['hvamenerpartiet.digitalisering.programmappe' => $this->mappe]);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->mappe.'/*/*') as $fil) {
            unlink($fil);
        }
        @rmdir($this->mappe.'/Stortingsprogram');
        @rmdir($this->mappe);

        parent::tearDown();
    }

    public function test_alle_programfiler_i_docs_er_koblet_til_et_program(): void
    {
        config(['hvamenerpartiet.digitalisering.programmappe' => base_path('docs/Partiprogram')]);
        $this->seed(DatabaseSeeder::class);

        $medFil = Program::query()->whereNotNull('kildefil')->get();
        $this->assertGreaterThanOrEqual(10, $medFil->count());
        foreach ($medFil as $program) {
            $this->assertTrue($program->kildefilFinnes(), "{$program->nokkel}: finner ikke {$program->kildefil}");
        }
        $this->assertSame([], app(Programmappe::class)->utenProgram());
    }

    public function test_flyten_gar_av_seg_selv_fram_til_gjennomgang_og_videre_etter_godkjenning(): void
    {
        $this->krevPdftotext();
        $ollama = FalskOllama::start();
        $ollama->chatsvar = array_fill(0, 10, ['standpunkt' => 'for', 'sikkerhet' => 'høy', 'kildesetninger' => ['tp-ap2025-k1-a1-s1'], 'begrunnelse' => 'Teksten sier det.']);
        $program = $this->lagProgram('tp');
        $tema = Tema::create(['navn' => 'NATO', 'slug' => 'nato', 'pastand' => 'Norge bør være medlem av NATO.', 'synonymer' => ['NATO'], 'status' => 'godkjent']);
        $planlegger = app(Prosessplanlegger::class);

        // Med synkron kø kjører jobbene med en gang: innlesing, så digitalisering.
        $planlegger->planlegg();
        $planlegger->planlegg();

        $program->refresh();
        $this->assertSame(Digitaliseringsstatus::Digitalisert, $program->digitalisering_status);
        $this->assertSame(
            [[Prosessteg::Innlesing, Kjoringsstatus::Ferdig], [Prosessteg::Digitalisering, Kjoringsstatus::Ferdig]],
            Prosesskjoring::orderBy('id')->get()->map(fn ($k) => [$k->steg, $k->status])->all(),
        );

        // Gjennomgangen er manuell: ingenting skjer før strukturen er godkjent.
        $this->assertSame(0, array_sum($planlegger->planlegg()));

        app(Prosessflyt::class)->godkjennStruktur($program);
        $planlegger->planlegg();

        $standpunkt = Standpunkt::sole();
        $this->assertSame($tema->id, $standpunkt->tema_id);
        $this->assertSame('for', $standpunkt->verdi->value);
        $this->assertSame(Temastatus::Analysert, $tema->refresh()->status);
        $this->assertSame(0, Prosesskjoring::where('status', '!=', Kjoringsstatus::Ferdig)->count());
        $this->assertSame(['innlesing', 'digitalisering', 'embeddings', 'klassifisering'], Prosesskjoring::orderBy('id')->pluck('steg')->map->value->all());
    }

    public function test_planleggeren_starter_ikke_mer_enn_kapasiteten(): void
    {
        Queue::fake();
        config(['hvamenerpartiet.flyt.kapasitet.import' => 2]);
        foreach (['aa', 'bb', 'cc'] as $slug) {
            $this->lagProgram($slug);
        }
        $planlegger = app(Prosessplanlegger::class);

        $this->assertSame(2, $planlegger->planlegg()['innlesing']);
        Queue::assertPushed(LesInnProgram::class, 2);
        $this->assertSame(0, $planlegger->ledig('import'));

        // Ingen ledig kapasitet før en av jobbene er ferdig.
        $this->assertSame(0, array_sum($planlegger->planlegg()));

        Prosesskjoring::first()->update(['status' => Kjoringsstatus::Ferdig]);
        $this->assertSame(1, $planlegger->planlegg()['innlesing']);
        Queue::assertPushed(LesInnProgram::class, 3);
    }

    public function test_historiske_programmer_venter_til_gjeldende_er_ferdige_i_gruppen(): void
    {
        Queue::fake();
        config(['hvamenerpartiet.flyt.kapasitet.import' => 1]);
        $gammel = $this->lagProgram('gg', gjeldende: false);
        $ny = $this->lagProgram('nn');
        $planlegger = app(Prosessplanlegger::class);

        $this->assertFalse($planlegger->historiskeSlippesTil(Prosessteg::Innlesing));
        $this->assertSame([$ny->id], array_map(fn ($k) => $k[0]->id, $planlegger->kandidater(Prosessteg::Innlesing, 5)));

        // Det gjeldende programmet venter på digitalisering, og det holder
        // fortsatt de historiske igjen i import-gruppen.
        $ny->update(['filhash' => hash_file('sha256', $ny->kildefilSti()), 'lastet_ned' => now()->addMinute(), 'filsti' => 'x.pdf', 'digitalisering_status' => Digitaliseringsstatus::LastetNed]);
        Storage::disk('local')->put('x.pdf', '%PDF');
        $this->assertFalse($planlegger->historiskeSlippesTil(Prosessteg::Innlesing));

        // Når det gjeldende venter på manuell gjennomgang, slippes de historiske til.
        $ny->update(['digitalisering_status' => Digitaliseringsstatus::Digitalisert]);
        $this->assertTrue($planlegger->historiskeSlippesTil(Prosessteg::Innlesing));
        $this->assertSame(1, $planlegger->planlegg()['innlesing']);
        $this->assertSame($gammel->id, Prosesskjoring::first()->program_id);
    }

    public function test_historiske_programmer_kan_slas_av(): void
    {
        Queue::fake();
        config(['hvamenerpartiet.flyt.historiske' => false]);
        $this->lagProgram('gg', gjeldende: false);

        $this->assertSame([], app(Prosessplanlegger::class)->kandidater(Prosessteg::Innlesing, 5));
    }

    public function test_seederen_kobler_bare_til_filer_som_finnes(): void
    {
        config(['hvamenerpartiet.digitalisering.programmappe' => base_path('docs/Partiprogram')]);
        $this->seed(DatabaseSeeder::class);

        $data = collect(require database_path('data/programmer.php'));
        $this->assertSame($data->count(), Program::count());
        $this->assertSame($data->where('gjeldende', false)->count(), Program::where('gjeldende', false)->count());
        foreach ($data as $p) {
            $this->assertNotNull($p['kildefil'] ?? null, "{$p['parti']} {$p['tittel']} mangler planlagt filsti");
        }
        foreach (Program::all() as $program) {
            $this->assertTrue($program->kildefil === null || $program->kildefilFinnes(), $program->nokkel);
        }
    }

    public function test_samme_steg_startes_ikke_to_ganger_for_samme_program(): void
    {
        Queue::fake();
        $program = $this->lagProgram('tp');
        $flyt = app(Prosessflyt::class);

        $forste = $flyt->start(Prosessteg::Digitalisering, $program);
        $andre = $flyt->start(Prosessteg::Digitalisering, $program);

        $this->assertSame($forste->id, $andre->id);
        Queue::assertPushed(DigitaliserProgram::class, 1);
    }

    public function test_feilet_innlesing_startes_ikke_automatisk_igjen_for_filen_er_endret(): void
    {
        $program = $this->lagProgram('tp');
        file_put_contents($this->mappe.'/'.$program->kildefil, 'ikke en pdf');
        touch($this->mappe.'/'.$program->kildefil, time() - 60);
        $planlegger = app(Prosessplanlegger::class);

        $planlegger->planlegg();
        $kjoring = Prosesskjoring::sole();
        $this->assertSame(Kjoringsstatus::Feilet, $kjoring->status);
        $this->assertStringContainsString('ikke en PDF', $kjoring->melding);

        $this->assertSame(0, array_sum($planlegger->planlegg()));

        copy(base_path('tests/Fixtures/testprogram.pdf'), $this->mappe.'/'.$program->kildefil);
        touch($this->mappe.'/'.$program->kildefil, time() + 5);
        $this->assertSame(1, $planlegger->planlegg()['innlesing']);
    }

    public function test_kjoringer_som_har_stoppet_opp_regnes_som_feilet(): void
    {
        $program = $this->lagProgram('tp');
        $kjoring = Prosesskjoring::create([
            'steg' => Prosessteg::Digitalisering, 'program_id' => $program->id, 'status' => Kjoringsstatus::Kjorer,
            'utlost_av' => Utlosning::Automatisk, 'startet' => now()->subHour(),
        ]);

        $this->assertSame(1, app(Prosessplanlegger::class)->ryddOpp());
        $this->assertSame(Kjoringsstatus::Feilet, $kjoring->refresh()->status);
    }

    public function test_embeddingserver_uten_stotte_feiler_med_en_gang_og_forklarer_hvorfor(): void
    {
        $this->krevPdftotext();
        $program = $this->lagProgram('tp');
        app(Prosessplanlegger::class)->planlegg();
        app(Prosessplanlegger::class)->planlegg();
        $this->assertGreaterThan(0, $program->tekstbiter()->count());

        Http::fake(['*' => Http::response(['error' => 'This server does not support embeddings. Start it with `--embeddings`'], 501)]);
        $kjoring = Prosesskjoring::create([
            'steg' => Prosessteg::Embeddings, 'program_id' => $program->id, 'status' => Kjoringsstatus::IKo,
            'utlost_av' => Utlosning::Admin,
        ]);

        $jobb = (new LagEmbeddings($program))->medKjoring($kjoring)->withFakeQueueInteractions();
        $jobb->handle(app(Embedder::class));

        $jobb->assertFailed();
        $jobb->assertNotReleased();

        $kjoring->refresh();
        $this->assertSame(Kjoringsstatus::Feilet, $kjoring->status);
        $this->assertStringNotContainsString('Prøver igjen', $kjoring->melding);
        $this->assertStringContainsString('--embeddings', $kjoring->melding);
    }

    public function test_automatisk_drift_kan_slas_av(): void
    {
        Queue::fake();
        $this->lagProgram('tp');

        $this->artisan('flyt:planlegg', ['--av' => true])->assertSuccessful();
        $this->assertFalse(app(Prosessplanlegger::class)->erAutomatisk());
        Queue::assertNothingPushed();

        $this->artisan('flyt:planlegg', ['--tving' => true])->assertSuccessful();
        Queue::assertPushed(LesInnProgram::class, 1);
    }

    public function test_adminsidene_vises_og_strukturen_kan_godkjennes(): void
    {
        $this->krevPdftotext();
        FalskOllama::start();
        $admin = User::factory()->create(['er_admin' => true]);
        $program = $this->lagProgram('tp');
        app(Prosessplanlegger::class)->planlegg();
        app(Prosessplanlegger::class)->planlegg();
        $this->assertSame(Digitaliseringsstatus::Digitalisert, $program->refresh()->digitalisering_status);

        $this->actingAs($admin);
        foreach (['/admin', '/admin/programmer', "/admin/programmer/{$program->id}", '/admin/kjoringer', '/admin/temaer', '/admin/standpunkter', "/admin/programfil/{$program->id}"] as $side) {
            $this->get($side)->assertOk();
        }

        Livewire::test(VisProgram::class, ['record' => $program->id])
            ->callAction('godkjennStruktur')
            ->assertHasNoErrors();

        $this->assertSame(Digitaliseringsstatus::Godkjent, $program->refresh()->digitalisering_status);
        $this->assertTrue(Prosesskjoring::where('steg', Prosessteg::Embeddings)->where('status', Kjoringsstatus::Ferdig)->exists());
    }

    private function lagProgram(string $slug, bool $gjeldende = true): Program
    {
        $parti = Parti::create(['navn' => "Parti {$slug}", 'forkortelse' => strtoupper($slug), 'slug' => $slug]);
        $fil = "Stortingsprogram/{$slug}.pdf";
        copy(base_path('tests/Fixtures/testprogram.pdf'), $this->mappe.'/'.$fil);

        return Program::create([
            'parti_id' => $parti->id, 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2025–2029',
            'slug' => 'arbeidsprogram-2025', 'nokkel' => "{$slug}-ap2025", 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
            'kildefil' => $fil, 'gjeldende' => $gjeldende,
        ]);
    }

    private function krevPdftotext(): void
    {
        if (! trim((string) shell_exec('command -v pdftotext'))) {
            $this->markTestSkipped('pdftotext (poppler-utils) er ikke installert.');
        }
    }
}
