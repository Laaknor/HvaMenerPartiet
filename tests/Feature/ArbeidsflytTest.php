<?php

namespace Tests\Feature;

use App\Enums\Digitaliseringsstatus;
use App\Models\Parti;
use App\Models\Program;
use App\Models\Standpunkt;
use App\Models\Tema;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FalskOllama;
use Tests\TestCase;

/** Hele fase 1-løypa med Artisan-kommandoene, med falsk Ollama. */
class ArbeidsflytTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_lager_ni_partier_og_programmene_deres(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(9, Parti::count());
        $this->assertSame(15, Program::where('gjeldende', true)->count());
        $this->assertSame(2, Program::where('gjeldende', true)->where('type', 'kombinert')->count());
        $this->assertTrue(Program::where('nokkel', 'ap-ap2021')->where('gjeldende', false)->exists());
        $this->assertTrue(Program::where('nokkel', 'sv-pp2023')->exists());
        $this->assertFalse(Parti::where('slug', 'ap')->first()->load('programmer')->harPrinsipprogram());
        $this->assertTrue(Parti::where('slug', 'sp')->first()->load('programmer')->harPrinsipprogram());
        $this->assertGreaterThanOrEqual(6, Tema::count());

        // Seederen kan kjøres flere ganger.
        $antall = Program::count();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($antall, Program::count());
    }

    public function test_fra_pdf_til_rapport(): void
    {
        if (! trim((string) shell_exec('command -v pdftotext'))) {
            $this->markTestSkipped('pdftotext (poppler-utils) er ikke installert.');
        }

        Storage::fake('local');
        $ollama = FalskOllama::start();
        $ollama->chatsvar = array_fill(0, 50, fn (array $data) => [
            'standpunkt' => 'for',
            'sikkerhet' => 'høy',
            'kildesetninger' => [str_contains(end($data['messages'])['content'], 'Påstand: «Norge bør være medlem av NATO.»') ? 'tp-ap2025-k1-a1-s1' : 'tp-ap2025-k2-a1-s1'],
            'begrunnelse' => 'Teksten sier det.',
        ]);

        $parti = Parti::create(['navn' => 'Testpartiet', 'forkortelse' => 'TP', 'slug' => 'tp']);
        Program::create([
            'parti_id' => $parti->id, 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2025–2029',
            'slug' => 'arbeidsprogram-2025', 'nokkel' => 'tp-ap2025', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        ]);
        Tema::create(['navn' => 'NATO', 'slug' => 'nato', 'pastand' => 'Norge bør være medlem av NATO.', 'synonymer' => ['NATO'], 'status' => 'godkjent']);

        $this->artisan('program:importer-fil', ['program' => 'tp-ap2025', 'fil' => base_path('tests/Fixtures/testprogram.pdf')])->assertSuccessful();
        $this->artisan('program:digitaliser', ['program' => ['tp-ap2025'], '--synkront' => true])->assertSuccessful();
        $this->artisan('program:vis', ['program' => 'tp-ap2025', '--setninger' => true])
            ->expectsOutputToContain('tp-ap2025-k1-a1-s1')
            ->assertSuccessful();

        // Analyse før godkjenning skal ikke skje.
        $this->artisan('tema:klassifiser', ['tema' => ['nato'], '--synkront' => true])->assertFailed();

        $this->artisan('program:godkjenn', ['program' => ['tp-ap2025'], '--uten-embeddings' => true])->assertSuccessful();
        $this->assertSame(Digitaliseringsstatus::Godkjent, Program::first()->digitalisering_status);

        $this->artisan('program:embeddings', ['program' => ['tp'], '--synkront' => true])->assertSuccessful();
        $this->artisan('tema:klassifiser', ['tema' => ['nato'], '--synkront' => true])->assertSuccessful();

        $standpunkt = Standpunkt::firstOrFail();
        $this->assertSame('for', $standpunkt->verdi->value);
        $this->assertSame('Norge skal være et aktivt medlem av NATO, og vi vil styrke forsvaret.', $standpunkt->utdrag());

        $fil = sys_get_temp_dir().'/hvamenerpartiet-rapport-'.uniqid().'.md';
        $this->artisan('prototype:rapport', ['--fil' => $fil])->assertSuccessful();
        $rapport = file_get_contents($fil);
        unlink($fil);

        $this->assertStringContainsString('## NATO', $rapport);
        $this->assertStringContainsString('| TP | For | Høy | Ingen prinsipprogram | Norge skal være et aktivt medlem av NATO', $rapport);
        $this->assertStringContainsString('`/program/tp/arbeidsprogram-2025/kap-1#a1-s1`', $rapport);
        $this->artisan('program:status')->assertSuccessful();
    }
}
