<?php

namespace Tests\Feature;

use App\Models\Parti;
use App\Models\User;
use App\Sidecache\Sidecache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SidecacheTest extends TestCase
{
    use RefreshDatabase;

    private int $visninger = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sidecache.aktiv' => true, 'sidecache.deployment' => 'a1']);

        Route::middleware('web')->group(function () {
            Route::get('teller', function () {
                $this->visninger++;

                return response("Visning {$this->visninger}");
            });
            Route::get('skjema', fn () => '<form><input name="_token" value="'.csrf_token().'"></form>');
            Route::get('mangler', fn () => abort(404));
        });
    }

    public function test_offentlige_sider_hentes_fra_cachen_andre_gang(): void
    {
        $this->get('/teller')->assertSee('Visning 1')->assertHeader('X-Sidecache', 'lagret');
        $this->get('/teller')->assertSee('Visning 1')->assertHeader('X-Sidecache', 'treff');
        $this->assertSame(1, $this->visninger);
    }

    public function test_sporingsparametere_gir_samme_side(): void
    {
        $this->get('/teller?utm_source=x');
        $this->get('/teller?mtm_campaign=y&fbclid=z')->assertHeader('X-Sidecache', 'treff');
        $this->get('/teller?q=nato')->assertHeader('X-Sidecache', 'lagret');
    }

    public function test_cachen_varer_i_24_timer(): void
    {
        $this->get('/teller');
        $this->travel(23)->hours();
        $this->get('/teller')->assertSee('Visning 1');
        $this->travel(2)->hours();
        $this->get('/teller')->assertSee('Visning 2');
    }

    public function test_endret_innhold_tommer_cachen(): void
    {
        $this->get('/teller');
        Parti::create(['navn' => 'Testpartiet', 'forkortelse' => 'TP', 'slug' => 'tp']);
        $this->get('/teller')->assertSee('Visning 2');
    }

    public function test_endringer_i_en_transaksjon_tommer_cachen_etter_commit(): void
    {
        $this->get('/teller');
        $versjon = app(Sidecache::class)->versjon();

        DB::transaction(function () use ($versjon) {
            Parti::create(['navn' => 'A', 'forkortelse' => 'A', 'slug' => 'a']);
            Parti::create(['navn' => 'B', 'forkortelse' => 'B', 'slug' => 'b']);
            $this->assertSame($versjon, app(Sidecache::class)->versjon());
        });

        $this->assertSame($versjon + 1, app(Sidecache::class)->versjon());
    }

    public function test_ny_deployment_gir_ferske_sider(): void
    {
        $this->get('/teller');
        config(['sidecache.deployment' => 'b2']);
        $this->app->forgetInstance(Sidecache::class);
        $this->get('/teller')->assertSee('Visning 2');
    }

    public function test_kommandoen_og_optimize_tommer_cachen(): void
    {
        $this->get('/teller');
        $this->artisan('sidecache:tom')->assertSuccessful();
        $this->get('/teller')->assertSee('Visning 2');

        $this->artisan('optimize')->assertSuccessful();
        $this->artisan('optimize:clear')->assertSuccessful();
        $this->get('/teller')->assertSee('Visning 3');
    }

    public function test_innloggede_far_alltid_fersk_side(): void
    {
        $this->get('/teller');
        $this->actingAs(User::factory()->create())->get('/teller')->assertSee('Visning 2')->assertHeaderMissing('X-Sidecache');
    }

    public function test_feilsider_lagres_ikke(): void
    {
        $this->get('/mangler')->assertNotFound()->assertHeaderMissing('X-Sidecache');
    }

    public function test_admin_og_horizon_caches_ikke(): void
    {
        $this->get('/admin/login')->assertHeaderMissing('X-Sidecache');
        $this->get('/horizon')->assertHeaderMissing('X-Sidecache');
    }

    public function test_csrf_token_er_per_besokende(): void
    {
        $this->withSession(['_token' => 'token-en'])->get('/skjema')->assertSee('value="token-en"', false);

        $this->flushSession();
        $this->withSession(['_token' => 'token-to'])->get('/skjema')
            ->assertHeader('X-Sidecache', 'treff')
            ->assertSee('value="token-to"', false)
            ->assertDontSee('token-en');
    }

    public function test_etag_gir_304_nar_siden_er_uendret(): void
    {
        $etag = $this->get('/teller')->headers->get('ETag');

        $this->get('/teller', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_robots_og_sitemap_caches_ogsa(): void
    {
        $this->get('/sitemap.xml')->assertHeader('X-Sidecache', 'lagret');
        $this->get('/sitemap.xml')->assertHeader('X-Sidecache', 'treff')->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    }
}
