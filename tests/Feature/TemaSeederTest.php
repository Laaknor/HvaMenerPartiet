<?php

namespace Tests\Feature;

use App\Enums\Temastatus;
use App\Models\Tema;
use Database\Seeders\TemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** TemaSeeder kjøres ved hver deploy, og må ikke overskrive det som er endret i admin. */
class TemaSeederTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $endringer = []): array
    {
        return [[
            'slug' => 'bompenger',
            'navn' => 'Bompenger',
            'beskrivelse' => 'Bompenger og veiprising.',
            'pastand' => 'Bruken av bompenger bør reduseres.',
            'synonymer' => ['bompenger', 'bomring'],
            'forvekslinger' => ['drivstoffavgift'],
            ...$endringer,
        ]];
    }

    public function test_datafilen_har_unike_slugger_og_alle_felter(): void
    {
        $temaer = require database_path('data/temaer.php');
        $slugger = array_column($temaer, 'slug');

        $this->assertSame(count($slugger), count(array_unique($slugger)));
        foreach ($temaer as $tema) {
            $this->assertNotEmpty($tema['navn'], $tema['slug']);
            $this->assertNotEmpty($tema['pastand'], $tema['slug']);
            $this->assertNotEmpty($tema['synonymer'], $tema['slug']);
        }
    }

    public function test_seederen_kan_kjores_flere_ganger(): void
    {
        $this->seed(TemaSeeder::class);
        $antall = Tema::count();
        $this->seed(TemaSeeder::class);

        $this->assertSame($antall, Tema::count());
        $this->assertTrue(Tema::where('slug', 'havvind')->exists());
    }

    public function test_nye_temaer_godkjennes_og_status_beholdes(): void
    {
        $seeder = new TemaSeeder;
        $seeder->leggInn($this->data());
        $this->assertSame(Temastatus::Godkjent, Tema::firstOrFail()->status);

        Tema::firstOrFail()->update(['status' => Temastatus::Publisert]);
        $seeder->leggInn($this->data());
        $this->assertSame(Temastatus::Publisert, Tema::firstOrFail()->status);
    }

    public function test_felt_endret_i_admin_overskrives_ikke(): void
    {
        $seeder = new TemaSeeder;
        $seeder->leggInn($this->data());

        Tema::firstOrFail()->update([
            'pastand' => 'Bompenger bør fjernes.',
            'synonymer' => ['bompenger', 'bomring', 'bomstasjon'],
        ]);

        // Også når datafilen endrer de samme feltene, vinner admin.
        $seeder->leggInn($this->data([
            'pastand' => 'Det bør kreves mindre bompenger.',
            'synonymer' => ['bompenger'],
            'beskrivelse' => 'Ny beskrivelse.',
        ]));

        $tema = Tema::firstOrFail();
        $this->assertSame('Bompenger bør fjernes.', $tema->pastand);
        $this->assertSame(['bompenger', 'bomring', 'bomstasjon'], $tema->synonymer);
        // Beskrivelsen er ikke rørt i admin, så den følger datafilen.
        $this->assertSame('Ny beskrivelse.', $tema->beskrivelse);

        // Admins versjon står seg også ved neste kjøring.
        $seeder->leggInn($this->data(['pastand' => 'Det bør kreves mindre bompenger.']));
        $this->assertSame('Bompenger bør fjernes.', Tema::firstOrFail()->pastand);
    }

    public function test_tomt_felt_fra_admin_regnes_ikke_som_endring_fra_tom_seed(): void
    {
        $seeder = new TemaSeeder;
        $seeder->leggInn($this->data(['forvekslinger' => null]));
        Tema::firstOrFail()->update(['forvekslinger' => []]);

        $seeder->leggInn($this->data(['forvekslinger' => ['drivstoffavgift']]));

        $this->assertSame(['drivstoffavgift'], Tema::firstOrFail()->forvekslinger);
    }

    public function test_tema_fra_for_seedverdiene_oppdateres_en_gang(): void
    {
        Tema::create([
            'slug' => 'bompenger', 'navn' => 'Bompenger', 'pastand' => 'Gammel påstand.',
            'synonymer' => ['bompenger'], 'status' => Temastatus::Analysert,
        ]);

        (new TemaSeeder)->leggInn($this->data());

        $tema = Tema::firstOrFail();
        $this->assertSame('Bruken av bompenger bør reduseres.', $tema->pastand);
        $this->assertSame(['drivstoffavgift'], $tema->forvekslinger);
        $this->assertSame(Temastatus::Analysert, $tema->status);
        $this->assertNotNull($tema->seedverdier);
    }
}
