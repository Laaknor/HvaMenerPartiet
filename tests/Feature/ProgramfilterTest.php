<?php

namespace Tests\Feature;

use App\Filament\Resources\Programmer\Pages\ListProgrammer;
use App\Filament\Resources\Programmer\ProgramResource;
use App\Models\Parti;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramfilterTest extends TestCase
{
    use RefreshDatabase;

    private Program $tpNy;

    private Program $tpGammel;

    private Program $tpPrinsipp;

    private Program $apNy;

    protected function setUp(): void
    {
        parent::setUp();

        $tp = Parti::create(['navn' => 'Testpartiet', 'forkortelse' => 'TP', 'slug' => 'tp']);
        $ap = Parti::create(['navn' => 'Andrepartiet', 'forkortelse' => 'AP', 'slug' => 'ap']);

        $this->tpNy = $this->program($tp, 'tp-ap2025', '2025-2029');
        $this->tpGammel = $this->program($tp, 'tp-ap2021', '2021-2025');
        $this->tpPrinsipp = $this->program($tp, 'tp-pp2019', null, 'prinsipprogram');
        $this->apNy = $this->program($ap, 'ap-ap2025', '2025-2029');

        $this->actingAs(User::factory()->create(['er_admin' => true]));
    }

    private function program(Parti $parti, string $nokkel, ?string $periode, string $type = 'arbeidsprogram'): Program
    {
        return Program::create([
            'parti_id' => $parti->id, 'type' => $type, 'tittel' => $nokkel, 'slug' => $nokkel, 'nokkel' => $nokkel,
            'periode' => $periode, 'gjeldende' => $periode !== '2021-2025',
        ]);
    }

    public function test_filtrerer_pa_parti(): void
    {
        Livewire::test(ListProgrammer::class)
            ->filterTable('parti', [$this->apNy->parti_id])
            ->assertCanSeeTableRecords([$this->apNy])
            ->assertCanNotSeeTableRecords([$this->tpNy, $this->tpGammel, $this->tpPrinsipp]);
    }

    public function test_filtrerer_pa_periode(): void
    {
        Livewire::test(ListProgrammer::class)
            ->filterTable('periode', ['2025-2029'])
            ->assertCanSeeTableRecords([$this->tpNy, $this->apNy])
            ->assertCanNotSeeTableRecords([$this->tpGammel, $this->tpPrinsipp]);
    }

    public function test_programmer_uten_periode_kan_velges(): void
    {
        Livewire::test(ListProgrammer::class)
            ->filterTable('periode', ['2021-2025', ProgramResource::UTEN_PERIODE])
            ->assertCanSeeTableRecords([$this->tpGammel, $this->tpPrinsipp])
            ->assertCanNotSeeTableRecords([$this->tpNy, $this->apNy]);
    }

    public function test_parti_og_periode_kombineres(): void
    {
        Livewire::test(ListProgrammer::class)
            ->filterTable('parti', [$this->tpNy->parti_id])
            ->filterTable('periode', ['2025-2029'])
            ->assertCanSeeTableRecords([$this->tpNy])
            ->assertCanNotSeeTableRecords([$this->apNy, $this->tpGammel, $this->tpPrinsipp]);
    }

    public function test_periodevalgene_har_nyeste_forst_og_uten_periode_sist(): void
    {
        $this->assertSame(
            ['2025-2029' => '2025-2029', '2021-2025' => '2021-2025', ProgramResource::UTEN_PERIODE => 'Uten periode (prinsipprogram)'],
            ProgramResource::periodevalg(),
        );
    }

    public function test_siden_viser_filtrene(): void
    {
        $this->get('/admin/programmer')->assertOk()->assertSee('Stortingsperiode')->assertSee('Parti');
    }
}
