<?php

namespace Tests\Unit;

use App\Digitalisering\Linje;
use App\Digitalisering\Sidefilter;
use App\Digitalisering\Strukturtolker;
use PHPUnit\Framework\TestCase;

class SidefilterTest extends TestCase
{
    private const BRODTEKST = 'Partiet vil styrke forsvaret og samarbeidet i NATO, bygge ut kystforsvaret i nord og sørge for at Heimevernet har nok folk og utstyr i hele landet.';

    /** @var list<Linje> */
    private array $linjer = [];

    private array $y = [];

    private function linje(int $side, string $tekst, float $hoyde = 11.0): void
    {
        $y = $this->y[$side] ??= 100.0;
        $this->linjer[] = new Linje($side, count($this->linjer), $tekst, 72, $y, 72 + mb_strlen($tekst) * 5, $y + $hoyde, 842);
        $this->y[$side] = $y + $hoyde + 4;
    }

    private function innholdsside(int $side, int $antall = 4): void
    {
        $this->linje($side, 'Kapittel '.$side, 20);
        for ($i = 0; $i < $antall; $i++) {
            $this->linje($side, self::BRODTEKST);
        }
    }

    private function vanligDokument(): void
    {
        $this->linje(1, 'Testpartiets program 2025–2029', 30);
        $this->linje(1, 'Vedtatt på landsmøtet 2025');
        $this->linje(2, 'Innhold', 20);
        $this->linje(2, 'Forord ........................ 3');
        $this->linje(2, '1 Forsvar og sikkerhet ........ 4');
        $this->linje(2, '2 Helse 5');
        $this->linje(2, '3 Skole');
        $this->linje(2, '6');
        for ($side = 3; $side <= 6; $side++) {
            $this->innholdsside($side);
        }
        $this->linje(7, 'Testpartiet');
        $this->linje(7, 'Postboks 123 Sentrum, 0102 Oslo');
        $this->linje(7, 'www.testpartiet.no');
    }

    public function test_finner_forside_innholdsfortegnelse_og_bakside(): void
    {
        $this->vanligDokument();

        $this->assertSame(
            [1 => 'forside', 2 => 'innholdsfortegnelse', 7 => 'bakside'],
            (new Sidefilter)->utelatteSider($this->linjer),
        );
    }

    public function test_innholdsfortegnelse_uten_overskrift_over_flere_sider(): void
    {
        for ($side = 1; $side <= 2; $side++) {
            foreach (['Arbeid og næring', 'Helse og omsorg', 'Skole og utdanning', 'Klima og miljø', 'Forsvar', 'Samferdsel'] as $i => $tittel) {
                $this->linje($side, "{$tittel} ".(10 * $side + $i));
            }
        }
        for ($side = 3; $side <= 6; $side++) {
            $this->innholdsside($side);
        }

        $this->assertSame([1 => 'innholdsfortegnelse', 2 => 'innholdsfortegnelse'], (new Sidefilter)->utelatteSider($this->linjer));
    }

    public function test_beholder_forste_side_med_programtekst(): void
    {
        $this->linje(1, 'Testpartiets program 2025–2029', 30);
        for ($side = 1; $side <= 5; $side++) {
            $this->innholdsside($side);
        }

        $this->assertSame([], (new Sidefilter)->utelatteSider($this->linjer));
    }

    public function test_korte_dokumenter_har_ikke_omslag(): void
    {
        $this->linje(1, 'Kort uttalelse', 20);
        $this->linje(1, 'Partiet vil styrke forsvaret.');

        $this->assertSame([], (new Sidefilter)->utelatteSider($this->linjer));
    }

    public function test_strukturtolker_hopper_over_sidene_og_beholder_tittelen(): void
    {
        $this->vanligDokument();

        $dokument = (new Strukturtolker)->tolk($this->linjer, 7);

        $this->assertSame('Testpartiets program 2025–2029', $dokument->tittel);
        $this->assertSame(['Kapittel 3', 'Kapittel 4', 'Kapittel 5', 'Kapittel 6'], array_map(fn ($k) => $k->tittel, $dokument->kapitler));
        $tekst = implode("\n", array_map(fn ($k) => implode("\n", array_map(fn ($a) => $a->tekst, $k->avsnitt)), $dokument->kapitler));
        $this->assertStringNotContainsString('Postboks', $tekst);
        $this->assertStringNotContainsString('Vedtatt', $tekst);
        $this->assertStringNotContainsString('.....', $tekst);
        $this->assertSame('Utelatt side 1 (forside), 2 (innholdsfortegnelse), 7 (bakside).', $dokument->utelattBeskrivelse());
    }
}
