<?php

namespace Tests\Unit;

use App\Digitalisering\Tekstrens;
use PHPUnit\Framework\TestCase;

class TekstrensTest extends TestCase
{
    public function test_slar_sammen_orddeling(): void
    {
        $this->assertSame('vil utvide samarbeidet', Tekstrens::skjot('vil ut-', 'vide samarbeidet'));
    }

    public function test_beholder_ekte_bindestrek(): void
    {
        $this->assertSame('helse- og omsorgstjenester', Tekstrens::skjot('helse-', 'og omsorgstjenester'));
        $this->assertSame('EØS- Avtalen', Tekstrens::skjot('EØS-', 'Avtalen'));
    }

    public function test_fjerner_myk_bindestrek(): void
    {
        $this->assertSame('forsvarsbudsjettet', Tekstrens::skjot("forsvars\u{00AD}", 'budsjettet'));
    }

    public function test_vanlige_linjer_skjotes_med_mellomrom(): void
    {
        $this->assertSame('Norge skal være med', Tekstrens::skjot('Norge skal', 'være med'));
    }

    public function test_normaliserer_ligaturer_og_mellomrom(): void
    {
        $this->assertSame('offisiell tekst', Tekstrens::normaliser("o\u{FB03}siell\u{00A0}  tekst "));
    }
}
