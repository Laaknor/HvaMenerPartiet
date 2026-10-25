<?php

namespace Tests\Unit;

use App\Analyse\Visningsregel;
use App\Enums\Kildetype;
use App\Enums\Standpunktverdi as V;
use App\Models\Standpunkt;
use PHPUnit\Framework\TestCase;

class VisningsregelTest extends TestCase
{
    private function sp(V $verdi, Kildetype $type): Standpunkt
    {
        return (new Standpunkt)->forceFill(['verdi' => $verdi, 'kildetype' => $type]);
    }

    public function test_arbeidsprogram_gir_merkelapp_ved_samme_retning(): void
    {
        $v = (new Visningsregel)->vis($this->sp(V::For, Kildetype::Arbeidsprogram), $this->sp(V::DelvisFor, Kildetype::Prinsipprogram), true);

        $this->assertSame(V::For, $v->hovedverdi);
        $this->assertSame(Kildetype::Arbeidsprogram, $v->hovedkilde);
        $this->assertFalse($v->avvik);
        $this->assertSame('Delvis for', $v->prinsipielleSyn());
    }

    public function test_prinsipprogram_brukes_nar_arbeidsprogrammet_tier(): void
    {
        $v = (new Visningsregel)->vis($this->sp(V::IkkeOmtalt, Kildetype::Arbeidsprogram), $this->sp(V::Mot, Kildetype::Prinsipprogram), true);

        $this->assertSame(V::Mot, $v->hovedverdi);
        $this->assertSame(Kildetype::Prinsipprogram, $v->hovedkilde);
        $this->assertSame(Visningsregel::MERKNAD_FRA_PRINSIPP, $v->merknad);
    }

    public function test_motsatt_retning_gir_begge_merkelapper(): void
    {
        $v = (new Visningsregel)->vis($this->sp(V::For, Kildetype::Arbeidsprogram), $this->sp(V::Mot, Kildetype::Prinsipprogram), true);

        $this->assertSame(V::For, $v->hovedverdi);
        $this->assertTrue($v->avvik);
        $this->assertSame(Visningsregel::MERKNAD_AVVIK, $v->merknad);
    }

    public function test_avvik_slas_ikke_sammen_til_delt_uklart(): void
    {
        $v = (new Visningsregel)->vis($this->sp(V::Mot, Kildetype::Arbeidsprogram), $this->sp(V::DeltUklart, Kildetype::Prinsipprogram), true);

        $this->assertSame(V::Mot, $v->hovedverdi);
        $this->assertTrue($v->avvik);
    }

    public function test_ingen_omtaler_gir_ikke_omtalt(): void
    {
        $v = (new Visningsregel)->vis($this->sp(V::IkkeOmtalt, Kildetype::Arbeidsprogram), null, true);

        $this->assertSame(V::IkkeOmtalt, $v->hovedverdi);
        $this->assertNull($v->hovedkilde);
    }

    public function test_parti_uten_prinsipprogram_vises_ikke_som_ikke_omtalt(): void
    {
        $v = (new Visningsregel)->vis($this->sp(V::For, Kildetype::Arbeidsprogram), null, false);

        $this->assertSame('Ingen prinsipprogram', $v->prinsipielleSyn());
        $this->assertFalse($v->avvik);
    }
}
