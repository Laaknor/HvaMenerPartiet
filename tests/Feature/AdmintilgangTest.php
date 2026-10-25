<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdmintilgangTest extends TestCase
{
    use RefreshDatabase;

    public function test_bruker_uten_tilgang_slipper_ikke_inn_i_admin(): void
    {
        $bruker = User::factory()->create();

        $this->assertFalse($bruker->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($bruker)->get('/admin')->assertForbidden();
    }

    public function test_kommandoen_gir_tilgang_til_admin_og_horizon(): void
    {
        $bruker = User::factory()->create(['email' => 'lars@example.com']);

        $this->artisan('bruker:admin', ['epost' => 'lars@example.com'])->assertSuccessful();

        $bruker->refresh();
        $this->assertTrue($bruker->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue(Gate::forUser($bruker)->allows('viewHorizon'));
        $this->actingAs($bruker)->get('/admin')->assertOk();

        $this->artisan('bruker:admin', ['epost' => 'lars@example.com', '--fjern' => true])->assertSuccessful();
        $this->assertFalse($bruker->refresh()->er_admin);
    }

    public function test_kommandoen_lager_brukeren_om_den_ikke_finnes(): void
    {
        $this->artisan('bruker:admin', ['epost' => 'ny@example.com', '--navn' => 'Ny'])
            ->expectsQuestion('Passord for den nye brukeren', 'hemmelig123')
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'ny@example.com')->value('er_admin'));
    }
}
