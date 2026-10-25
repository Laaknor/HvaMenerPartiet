<?php

namespace Tests\Unit;

use App\Digitalisering\Delmerker;
use App\Digitalisering\Dokument;
use App\Digitalisering\DokumentKapittel;
use App\Enums\Programdel;
use PHPUnit\Framework\TestCase;

class DelmerkerTest extends TestCase
{
    public function test_merker_kapitler_ut_fra_overskriftene(): void
    {
        $dok = new Dokument([
            new DokumentKapittel('Forord', 1, 1),
            new DokumentKapittel('Del 1: Prinsipprogram', 1, 2),
            new DokumentKapittel('Mennesket og samfunnet', 1, 3),
            new DokumentKapittel('Frihet', 2, 3),
            new DokumentKapittel('Del 2: Handlingsprogram 2025–2029', 1, 10),
            new DokumentKapittel('Forsvar', 1, 11),
        ]);

        $resultat = (new Delmerker)->merk($dok);

        $this->assertSame([null, Programdel::Prinsipp, Programdel::Prinsipp, Programdel::Prinsipp, Programdel::Handling, Programdel::Handling], array_map(fn ($k) => $k->del, $dok->kapitler));
        $this->assertSame(['merket' => 5, 'umerket' => 1], $resultat);
    }

    public function test_dokumenttittel_med_begge_ord_er_ikke_et_skifte(): void
    {
        $this->assertNull((new Delmerker)->delFraOverskrift('Prinsipp- og handlingsprogram 2025–2029'));
    }
}
