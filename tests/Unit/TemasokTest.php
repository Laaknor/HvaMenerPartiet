<?php

namespace Tests\Unit;

use App\Models\Tema;
use App\Sok\Temasok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemasokTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            ['NATO', 'nato', ['forsvarsallianse']],
            ['Eiendomsskatt', 'eiendomsskatt', ['boligskatt']],
            ['Formuesskatt', 'formuesskatt', []],
            ['EØS-avtalen', 'eos', ['EØS']],
        ] as [$navn, $slug, $synonymer]) {
            Tema::create(['navn' => $navn, 'slug' => $slug, 'pastand' => '…', 'synonymer' => $synonymer, 'status' => 'publisert']);
        }
        Tema::create(['navn' => 'Atomkraft', 'slug' => 'atomkraft', 'pastand' => '…', 'status' => 'foreslatt']);
    }

    public function test_eksakt_treff_pa_navn_synonym_og_slug_uansett_store_bokstaver(): void
    {
        $sok = new Temasok;

        $this->assertSame('nato', $sok->eksakt('Nato')?->slug);
        $this->assertSame('nato', $sok->eksakt('  forsvarsallianse ')?->slug);
        $this->assertSame('eos', $sok->eksakt('eøs')?->slug);
        $this->assertSame('eos', $sok->eksakt('EØS-avtalen')?->slug);
        $this->assertNull($sok->eksakt('atomkraft'), 'Upubliserte temaer skal ikke treffes');
        $this->assertNull($sok->eksakt(''));
    }

    public function test_delvise_treff_og_naermeste_ved_skrivefeil(): void
    {
        $sok = new Temasok;

        $this->assertEqualsCanonicalizing(['eiendomsskatt', 'formuesskatt'], $sok->sok('skatt')->pluck('slug')->all());
        $this->assertSame(['eiendomsskatt'], $sok->sok('eiendom')->pluck('slug')->all());
        $this->assertSame('eiendomsskatt', $sok->naermeste('eiendomskatt')->first()?->slug);
        $this->assertSame('formuesskatt', $sok->naermeste('formueskatt')->first()?->slug);
        $this->assertTrue($sok->naermeste('bompenger')->isEmpty());
    }

    public function test_forslag_til_autofullforing(): void
    {
        $forslag = (new Temasok)->forslag();

        $this->assertContains('forsvarsallianse', $forslag);
        $this->assertContains('NATO', $forslag);
        $this->assertNotContains('Atomkraft', $forslag);
        $this->assertCount(count(array_unique(array_map('mb_strtolower', $forslag))), $forslag);
    }
}
