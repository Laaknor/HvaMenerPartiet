<?php

namespace Tests\Feature;

use App\Digitalisering\Digitaliserer;
use App\Digitalisering\Dokument;
use App\Digitalisering\DokumentAvsnitt;
use App\Digitalisering\DokumentKapittel;
use App\Digitalisering\Programhenter;
use App\Digitalisering\ProgramLagrer;
use App\Enums\Digitaliseringsstatus;
use App\Enums\Kildetype;
use App\Enums\Sikkerhet;
use App\Enums\Standpunktstatus;
use App\Enums\Standpunktverdi;
use App\Models\Parti;
use App\Models\Program;
use App\Models\Setning;
use App\Models\Standpunkt;
use App\Models\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DigitaliseringTest extends TestCase
{
    use RefreshDatabase;

    private function program(): Program
    {
        $parti = Parti::create(['navn' => 'Testpartiet', 'forkortelse' => 'TP', 'slug' => 'tp']);

        return Program::create([
            'parti_id' => $parti->id, 'type' => 'arbeidsprogram', 'tittel' => 'Arbeidsprogram 2025–2029',
            'slug' => 'arbeidsprogram-2025', 'nokkel' => 'tp-ap2025', 'periode' => '2025-2029', 'vedtatt_ar' => 2025,
        ]);
    }

    private function digitaliserTestprogram(): Program
    {
        if (! is_executable(trim((string) shell_exec('command -v pdftotext')))) {
            $this->markTestSkipped('pdftotext (poppler-utils) er ikke installert.');
        }

        Storage::fake('local');
        $program = $this->program();
        app(Programhenter::class)->importerFil($program, base_path('tests/Fixtures/testprogram.pdf'));
        app(Digitaliserer::class)->digitaliser($program->refresh());

        return $program->refresh();
    }

    public function test_digitaliserer_pdf_til_kapitler_avsnitt_og_setninger(): void
    {
        $program = $this->digitaliserTestprogram();

        $this->assertSame(Digitaliseringsstatus::Digitalisert, $program->digitalisering_status);
        $this->assertSame(2, $program->antall_sider);

        $kapitler = $program->kapitler()->get();
        $this->assertSame(
            ['1 Forsvar og sikkerhet', '1.1 Allierte og samarbeid', '2 Europa', '3 Skatt og avgifter'],
            $kapitler->pluck('tittel')->all(),
        );
        $this->assertSame(['kap-1', 'kap-1-1', 'kap-2', 'kap-3'], $kapitler->pluck('slug')->all());
        $this->assertSame($kapitler[0]->id, $kapitler[1]->overordnet_kapittel_id);

        // Topptekst, bunntekst og sidetall er fjernet.
        $alt = $program->avsnitt()->pluck('tekst')->implode("\n");
        $this->assertStringNotContainsString('Testpartiet – Arbeidsprogram', $alt);
        $this->assertDoesNotMatchRegularExpression('/^\d+$/m', $alt);

        // Orddeling over linjeskift er slått sammen.
        $this->assertTrue(Setning::where('tekst', 'like', '%vil derfor utvide det nordiske%')->exists());
    }

    public function test_setninger_far_faste_id_er_per_kapittel_og_avsnitt(): void
    {
        $program = $this->digitaliserTestprogram();

        $s = Setning::where('nokkel', 'tp-ap2025-k1-a1-s2')->firstOrFail();
        $this->assertSame('Forsvarsbudsjettet skal økes til minst to prosent av BNP, jf. NATOs mål.', $s->tekst);

        // Avsnitt nummereres per hovedkapittel, også gjennom underkapitler.
        $this->assertSame(
            'Testpartiet vil derfor utvide det nordiske forsvarssamarbeidet, f.eks. innen luftvern.',
            Setning::where('nokkel', 'tp-ap2025-k1-a2-s2')->value('tekst'),
        );
    }

    public function test_punktliste_blir_ett_avsnitt_med_innledning(): void
    {
        $program = $this->digitaliserTestprogram();

        $liste = $program->avsnitt()->where('er_liste', true)->firstOrFail();
        $setninger = $liste->setninger;

        $this->assertSame('Testpartiet vil:', $setninger[0]->tekst);
        $this->assertFalse($setninger[0]->er_punkt);
        $this->assertCount(4, $setninger);
        $this->assertTrue($setninger[2]->er_punkt);
        $this->assertSame('Kjøpe nye ubåter i samarbeid med Tyskland, og sørge for at vedlikeholdet skjer i Norge.', $setninger[2]->tekst);
    }

    public function test_avsnitt_som_fortsetter_pa_neste_side_settes_sammen(): void
    {
        $program = $this->digitaliserTestprogram();

        $avsnitt = $program->avsnitt()->where('tekst', 'like', 'Eiendomsskatten%')->firstOrFail();

        $this->assertSame(1, $avsnitt->side);
        $this->assertSame(2, $avsnitt->til_side);
        $this->assertStringEndsWith('og bunnfradraget skal økes.', $avsnitt->tekst);
        $this->assertCount(2, $avsnitt->setninger);
    }

    public function test_lager_tekstbiter_som_ikke_krysser_overskrifter(): void
    {
        $program = $this->digitaliserTestprogram();

        $biter = $program->tekstbiter()->get();
        $this->assertCount(4, $biter);
        $this->assertSame('1 Forsvar og sikkerhet > 1.1 Allierte og samarbeid', $biter[1]->overskriftssti);
    }

    public function test_ny_import_beholder_id_for_uendret_tekst(): void
    {
        $program = $this->program();
        $lagrer = app(ProgramLagrer::class);

        $dok = fn (array $avsnitt) => new Dokument([
            new DokumentKapittel('1 Forsvar', 1, 1, array_map(fn ($t) => new DokumentAvsnitt($t, 1, 1), $avsnitt)),
        ]);

        $lagrer->lagre($program, $dok(['Norge skal være med i NATO. Forsvaret skal styrkes.', 'Vi vil ha flere soldater.']));
        $gammel = Setning::where('tekst', 'Vi vil ha flere soldater.')->firstOrFail();
        $this->assertSame('tp-ap2025-k1-a2-s1', $gammel->nokkel);

        // Et standpunkt som peker på en setning som forsvinner, sendes tilbake til godkjenning.
        $tema = Tema::create(['navn' => 'NATO', 'slug' => 'nato', 'pastand' => 'Norge bør være medlem av NATO.']);
        $standpunkt = Standpunkt::create([
            'tema_id' => $tema->id, 'program_id' => $program->id, 'kildetype' => Kildetype::Arbeidsprogram,
            'verdi' => Standpunktverdi::For, 'sikkerhet' => Sikkerhet::Hoy, 'status' => Standpunktstatus::Godkjent,
        ]);
        $standpunkt->kildesetninger()->attach(Setning::where('tekst', 'Forsvaret skal styrkes.')->value('id'), ['rekkefolge' => 1]);

        // Nytt avsnitt foran, og én setning endret.
        $r = $lagrer->lagre($program, $dok(['Et nytt avsnitt.', 'Norge skal være med i NATO. Forsvaret skal styrkes mye.', 'Vi vil ha flere soldater.']));

        $flyttet = Setning::where('tekst', 'Vi vil ha flere soldater.')->firstOrFail();
        $this->assertSame($gammel->id, $flyttet->id);
        $this->assertSame('tp-ap2025-k1-a2-s1', $flyttet->nokkel, 'ID-en skal ikke endres selv om setningen har flyttet seg');
        $this->assertSame(3, $flyttet->avsnitt->nr);

        // En ny setning kan ikke ta en ID som fortsatt er i bruk av en beholdt setning.
        $this->assertSame('tp-ap2025-k1-a1-s1', Setning::where('tekst', 'Norge skal være med i NATO.')->value('nokkel'));
        $this->assertSame('tp-ap2025-k1-a1-s1-n2', Setning::where('tekst', 'Et nytt avsnitt.')->value('nokkel'));

        $this->assertSame(2, $r->beholdt);
        $this->assertSame(1, $r->fjernet);
        $this->assertSame(1, $r->berorteStandpunkter);
        $this->assertSame(Standpunktstatus::Maskinell, $standpunkt->refresh()->status);
        $this->assertNotEmpty($standpunkt->merknader);
        $this->assertSame(1, Program::count());
        $this->assertSame(4, Setning::count());
    }
}
