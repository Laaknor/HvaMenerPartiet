<?php

namespace Tests\Unit;

use App\Digitalisering\BboxLeser;
use App\Digitalisering\Dokument;
use App\Digitalisering\Strukturtolker;
use App\Digitalisering\Tekstrens;
use PHPUnit\Framework\TestCase;

/**
 * Punktlister der merket («a)», «•», «3.») er satt i større skrift enn teksten,
 * eller står i en egen blokk ved siden av teksten, som i Rødts, KrFs og MDGs
 * programmer for 2025–2029.
 */
class PunktlisteTest extends TestCase
{
    private const BRODTEKST = 'Partiet vil styrke folkestyret og sørge for at viktige beslutninger tas av folkevalgte organer i hele landet, ikke av byråkrater.';

    /** @var array<int, list<string>> side => blokker */
    private array $sider = [];

    /**
     * @param  list<array{0: string, 1: float, 2: float, 3?: float}>  $linjer  [tekst, x, y, høyde]; et merke i større skrift skrives «a)|16»
     */
    private function blokk(int $side, array $linjer): void
    {
        $xml = '<block>';
        foreach ($linjer as $l) {
            [$tekst, $x, $y] = $l;
            $hoyde = $l[3] ?? 10.6;
            $ord = '';
            $xMin = $x;
            $yMaxLinje = $y + $hoyde;
            $yMinLinje = $y;
            foreach (explode(' ', $tekst) as $w) {
                [$w, $merkehoyde] = array_pad(explode('|', $w), 2, null);
                $wy = $merkehoyde !== null ? $y - ($merkehoyde - $hoyde) / 2 : $y;
                $wh = $merkehoyde !== null ? (float) $merkehoyde : $hoyde;
                $yMinLinje = min($yMinLinje, $wy);
                $yMaxLinje = max($yMaxLinje, $wy + $wh);
                $bredde = mb_strlen($w) * 5;
                $ord .= sprintf('<word xMin="%.1f" yMin="%.1f" xMax="%.1f" yMax="%.1f">%s</word>', $xMin, $wy, $xMin + $bredde, $wy + $wh, htmlspecialchars($w));
                $xMin += $bredde + 3;
            }
            $xml .= sprintf('<line xMin="%.1f" yMin="%.1f" xMax="%.1f" yMax="%.1f">%s</line>', $x, $yMinLinje, $xMin, $yMaxLinje, $ord);
        }
        $this->sider[$side][] = $xml.'</block>';
    }

    private function brodtekst(int $side, float $y): void
    {
        $this->blokk($side, [[self::BRODTEKST, 77, $y], [self::BRODTEKST, 77, $y + 12], [self::BRODTEKST, 77, $y + 24]]);
    }

    private function tolk(): Dokument
    {
        $xhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><doc>';
        foreach ($this->sider as $blokker) {
            $xhtml .= '<page width="595" height="842"><flow>'.implode('', $blokker).'</flow></page>';
        }
        $uttrekk = (new BboxLeser)->tolk($xhtml.'</doc></body></html>');

        return (new Strukturtolker)->tolk($uttrekk['linjer'], $uttrekk['sider']);
    }

    /** @return list<string> */
    private function titler(Dokument $dokument): array
    {
        return array_map(fn ($k) => $k->tittel, $dokument->kapitler);
    }

    private function alleAvsnitt(Dokument $dokument): string
    {
        return implode("\n\n", array_map(fn ($a) => $a->tekst, array_merge(...array_map(fn ($k) => $k->avsnitt, $dokument->kapitler))));
    }

    private function rodtSide(int $side): void
    {
        $this->blokk($side, [['Folkestyre og ytringsfrihet', 77, 86, 40]]);
        $this->brodtekst($side, 200);
        $this->blokk($side, [['Et fornyet folkestyre', 77, 466, 22]]);
        $this->blokk($side, [['RØDT VIL:', 77, 484]]);
        // Merkene a) og b) står i en egen blokk, i større skrift.
        $this->blokk($side, [['a)', 77, 494, 16], ['b)', 77, 506, 16]]);
        $this->blokk($side, [
            ['Styrke den folkevalgte styringen av staten.', 94, 496.5],
            ['Innføre lobbyregister på alle forvaltningsnivåer. Styrke karantenereglene for å stenge svingdøra mellom politikk og', 94, 508.5],
            ['påvirkningsbransjen.', 94, 520],
            // Fra c) står merket på samme linje som teksten, fortsatt i større skrift.
            ['c)|16 Kutte politikerlønningene for å minske gapet mellom folk og de folkevalgte.', 77, 532],
            ['d)|16 Avvikle monarkiet og innføre republikk. Dette må skje etter en folkeavstemning.', 77, 544],
            ['e)|16 Avholde flere rådgivende folkeavstemninger.', 77, 556],
            ['f)|16 At offentlighetsloven skal gjelde for selskaper med', 77, 568],
            ['offentlig aksjemajoritet.', 94, 580],
        ]);
    }

    public function test_bokstavpunkter_i_storre_skrift_blir_listepunkter_ikke_overskrifter(): void
    {
        for ($side = 1; $side <= 4; $side++) {
            $this->rodtSide($side);
        }

        $dokument = $this->tolk();

        foreach ($this->titler($dokument) as $tittel) {
            $this->assertDoesNotMatchRegularExpression('/\b[a-f]\)/u', $tittel);
        }

        $alt = $this->alleAvsnitt($dokument);
        $this->assertStringContainsString(
            "RØDT VIL:\n• Styrke den folkevalgte styringen av staten.\n• Innføre lobbyregister på alle forvaltningsnivåer. Styrke karantenereglene for å stenge svingdøra mellom politikk og påvirkningsbransjen.\n• Kutte politikerlønningene for å minske gapet mellom folk og de folkevalgte.\n• Avvikle monarkiet og innføre republikk. Dette må skje etter en folkeavstemning.\n• Avholde flere rådgivende folkeavstemninger.\n• At offentlighetsloven skal gjelde for selskaper med offentlig aksjemajoritet.",
            $alt,
        );
    }

    public function test_tallmerker_i_egen_blokk_festes_til_teksten(): void
    {
        for ($side = 1; $side <= 4; $side++) {
            $this->blokk($side, [['1.5 Plast', 57, 60, 31]]);
            $this->brodtekst($side, 120);
            $this->blokk($side, [['MDG vil:', 57, 200]]);
            $this->blokk($side, [['1.', 57, 228]]);
            $this->blokk($side, [['At Norge bidrar til en sterk global plastavtale.', 79, 228]]);
            $this->blokk($side, [['2.', 57, 256]]);
            $this->blokk($side, [['Utvide dagens produsentansvarsordninger, blant annet til å gjelde', 79, 256], ['all plast.', 79, 268]]);
            $this->blokk($side, [['•', 57, 296], ['•', 57, 310]]);
            $this->blokk($side, [['Forby engangsgriller.', 72, 296], ['Styrke norsk forskning på miljøgifter.', 72, 310]]);
        }

        $alt = $this->alleAvsnitt($this->tolk());

        $this->assertStringContainsString(
            "MDG vil:\n• At Norge bidrar til en sterk global plastavtale.\n• Utvide dagens produsentansvarsordninger, blant annet til å gjelde all plast.\n• Forby engangsgriller.\n• Styrke norsk forskning på miljøgifter.",
            $alt,
        );
        $this->assertDoesNotMatchRegularExpression('/^(\d+\.|•)$/mu', $alt);
    }

    public function test_korte_nummererte_overskrifter_med_parentes_er_fortsatt_overskrifter(): void
    {
        for ($side = 1; $side <= 4; $side++) {
            $this->blokk($side, [[$side.') Vi tar Norge videre', 77, 60, 22]]);
            $this->brodtekst($side, 100);
        }

        $this->assertContains('2) Vi tar Norge videre', $this->titler($this->tolk()));
    }

    public function test_punktmerker(): void
    {
        foreach (['•', 'o', 'a)', '(b)', '3)', '12.', 'ƽ', "\u{F0B7}"] as $merke) {
            $this->assertTrue(Tekstrens::erPunktmerke($merke), $merke);
        }
        foreach (['Norge', '2025', 'a', '1.5', 'Ja.'] as $tekst) {
            $this->assertFalse(Tekstrens::erPunktmerke($tekst), $tekst);
        }
    }
}
