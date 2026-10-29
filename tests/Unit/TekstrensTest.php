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
        $this->assertSame('FN-pakten', Tekstrens::skjot('FN-', 'pakten'));
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

    public function test_fjerner_sidehenvisninger(): void
    {
        $this->assertSame('Vi vil styrke Heimevernet.', Tekstrens::fjernSidehenvisninger('Vi vil styrke Heimevernet (se side 12).'));
        $this->assertSame('Vi vil styrke Heimevernet.', Tekstrens::fjernSidehenvisninger('Vi vil styrke Heimevernet, se side 12.'));
        $this->assertSame('Vi vil styrke Heimevernet.', Tekstrens::fjernSidehenvisninger('Vi vil styrke Heimevernet (jf. s. 4–5).'));
        $this->assertSame('Vi vil styrke Heimevernet.', Tekstrens::fjernSidehenvisninger('Vi vil styrke Heimevernet (side 30).'));
        $this->assertSame('Vi vil styrke Heimevernet.', Tekstrens::fjernSidehenvisninger('Vi vil styrke Heimevernet. Les mer om forsvaret på side 30.'));
        $this->assertSame('', Tekstrens::fjernSidehenvisninger('Se også kapittelet om forsvar på sidene 30 og 31.'));
    }

    public function test_beholder_tall_som_ikke_er_sidehenvisninger(): void
    {
        $tekst = 'Forsvarsbudsjettet skal økes til 2 prosent innen 2030, og vi vil se på side- og bakkant.';
        $this->assertSame($tekst, Tekstrens::fjernSidehenvisninger($tekst));
        $this->assertSame('Vi vil bygge 12 nye skoler.', Tekstrens::fjernSidehenvisninger('Vi vil bygge 12 nye skoler.'));
    }
}
