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
use App\Feilsoking\Feilsoking;
use App\Feilsoking\Sensurerer;
use App\Filament\Resources\Standpunkter\Pages\ListStandpunkter;
use App\Models\Feilsokingssak;
use App\Models\Parti;
use App\Models\Program;
use App\Models\Standpunkt;
use App\Models\Tema;
use App\Models\Tilbakemelding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\FalskOllama;
use Tests\TestCase;

class FeilsokingTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-token-som-er-minst-tretti-to-tegn-langt';

    private Standpunkt $standpunkt;

    private int $githubStatus = 201;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hvamenerpartiet.analyse.kjoringer' => 2,
            'hvamenerpartiet.analyse.terskel' => 0.3,
            'hvamenerpartiet.feilsoking.token' => self::TOKEN,
            'hvamenerpartiet.feilsoking.github_token' => 'ghp_hemmeligGithubToken1234567890abcdef',
            'hvamenerpartiet.feilsoking.github_repo' => 'Laaknor/HvaMenerPartiet',
        ]);

        $ollama = FalskOllama::start();
        Http::fake(['api.github.com/*' => fn () => $this->githubStatus === 201
            ? Http::response(['number' => 42], 201)
            : Http::response(['message' => 'Bad credentials'], $this->githubStatus)]);

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

    public function test_pakken_har_vurdering_kilder_prompt_og_modellsvar(): void
    {
        $sak = app(Feilsoking::class)->send($this->standpunkt, 'Burde vært delvis for.');
        $p = $sak->pakke;

        $this->assertSame('standpunkt', $p['type']);
        $this->assertSame('for', $p['standpunkt']['verdi']);
        $this->assertSame('Norge skal være et aktivt medlem av NATO.', $p['kildesetninger']['setninger'][0]['tekst']);
        $this->assertCount(2, $p['modellsvar']['kjoringer']);
        $this->assertSame(Klassifiserer::PROMPT_VERSJON, $p['prompt']['gjeldende_versjon']);
        $this->assertStringContainsString('[S1] Norge skal være et aktivt medlem av NATO.', $p['prompt']['meldinger'][1]['content']);
        $this->assertSame('Burde vært delvis for.', $p['notat']);
        $this->assertSame(42, $sak->github_issue);
    }

    public function test_hemmeligheter_og_e_post_kommer_aldri_med(): void
    {
        config(['app.key' => 'base64:superhemmeligappnokkel123456']);

        $sak = app(Feilsoking::class)->send($this->standpunkt, 'Se base64:superhemmeligappnokkel123456 og lars@example.com, token ghp_abcdefghijklmnopqrstuvwxyz123456');
        $json = json_encode($sak->pakke);

        $this->assertStringNotContainsString('superhemmeligappnokkel', $json);
        $this->assertStringNotContainsString('lars@example.com', $json);
        $this->assertStringNotContainsString('ghp_abcdefghijklmnopqrstuvwxyz123456', $json);
        $this->assertStringNotContainsString(self::TOKEN, $json);
        $this->assertStringContainsString(Sensurerer::SKJULT, $json);
    }

    public function test_issuen_har_bare_offentlige_deler(): void
    {
        $tilbakemelding = Tilbakemelding::create([
            'tema_id' => $this->standpunkt->tema_id, 'parti_id' => $this->standpunkt->program->parti_id,
            'standpunkt_id' => $this->standpunkt->id, 'tekst' => 'Privat tekst fra en besøkende',
        ]);

        $sak = app(Feilsoking::class)->send($tilbakemelding);

        $this->assertSame('Privat tekst fra en besøkende', $sak->pakke['tilbakemelding']['tekst']);
        Http::assertSent(function (Request $request) use ($sak) {
            if (! str_contains($request->url(), 'api.github.com')) {
                return false;
            }

            return $request->url() === 'https://api.github.com/repos/Laaknor/HvaMenerPartiet/issues'
                && $request->hasHeader('Authorization', 'Bearer ghp_hemmeligGithubToken1234567890abcdef')
                && $request['labels'] === ['feilsøking']
                && ! str_contains($request['body'], 'Privat tekst')
                && str_contains($request['body'], 'Norge skal være et aktivt medlem av NATO.')
                && str_contains($request['body'], $sak->apiUrl());
        });
    }

    public function test_pakken_lagres_selv_om_github_feiler(): void
    {
        $this->githubStatus = 401;

        $sak = app(Feilsoking::class)->send($this->standpunkt);

        $this->assertNull($sak->github_issue);
        $this->assertStringContainsString('401', $sak->github_feil);
        $this->assertDatabaseCount('feilsokingssaker', 1);
    }

    public function test_api_krever_token_og_gir_hele_pakken(): void
    {
        $sak = app(Feilsoking::class)->send($this->standpunkt);

        $this->getJson('/api/feilsoking/'.$sak->referanse)->assertUnauthorized();
        $this->getJson('/api/feilsoking/'.$sak->referanse, ['Authorization' => 'Bearer feil-token-som-er-langt-nok-1234567890'])->assertUnauthorized();

        $this->getJson('/api/feilsoking', ['Authorization' => 'Bearer '.self::TOKEN])
            ->assertOk()
            ->assertJsonPath('saker.0.referanse', $sak->referanse);

        $this->getJson('/api/feilsoking/'.$sak->referanse, ['Authorization' => 'Bearer '.self::TOKEN])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('pakke.standpunkt.verdi', 'for')
            ->assertJsonPath('github_issue', 'https://github.com/Laaknor/HvaMenerPartiet/issues/42');

        $this->assertNotNull($sak->refresh()->sist_hentet);
    }

    public function test_api_er_av_uten_token(): void
    {
        config(['hvamenerpartiet.feilsoking.token' => null]);

        $this->getJson('/api/feilsoking', ['Authorization' => 'Bearer '])->assertNotFound();
    }

    public function test_handlingen_i_admin_lager_sak(): void
    {
        $admin = User::factory()->create(['er_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(ListStandpunkter::class)
            ->callTableAction('sendTilFeilsoking', $this->standpunkt, ['notat' => 'Sjekk kildene', 'issue' => true])
            ->assertHasNoTableActionErrors();

        $sak = Feilsokingssak::sole();
        $this->assertSame('Sjekk kildene', $sak->notat);
        $this->assertSame($admin->id, $sak->opprettet_av);
        $this->assertSame(42, $sak->github_issue);
    }

    public function test_admin_viser_sakene_og_standpunktsiden(): void
    {
        $sak = app(Feilsoking::class)->send($this->standpunkt, 'Notat');
        $this->actingAs(User::factory()->create(['er_admin' => true]));

        $this->get('/admin/feilsoking')->assertOk()->assertSee('Notat');
        $this->get('/admin/standpunkter/'.$this->standpunkt->id)->assertOk()->assertSee('Send til feilsøking');
        $this->assertSame(42, $sak->github_issue);
    }
}
