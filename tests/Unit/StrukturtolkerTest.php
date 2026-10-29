<?php

namespace Tests\Unit;

use App\Digitalisering\DokumentKapittel;
use App\Digitalisering\Linje;
use App\Digitalisering\Spalteleser;
use App\Digitalisering\Strukturtolker;
use PHPUnit\Framework\TestCase;

class StrukturtolkerTest extends TestCase
{
    private const BRODTEKST = 'Partiet vil styrke forsvaret og samarbeidet i NATO, og sørge for nok folk og utstyr i hele landet.';

    /**
     * Sidene starter på 3, så den største skriften ikke regnes som
     * dokumenttittel på forsiden.
     *
     * @var list<Linje>
     */
    private array $linjer = [];

    private array $y = [];

    private int $blokk = 0;

    /** Legger til en linje under forrige linje på siden, som en egen blokk hvis $nyBlokk. */
    private function linje(int $side, string $tekst, float $hoyde = 11.0, float $x = 72, bool $nyBlokk = true, float $luft = 4): void
    {
        $y = $this->y[$side] ??= 100.0;
        $nyBlokk && $this->blokk++;
        $this->linjer[] = new Linje($side, $this->blokk, $tekst, $x, $y, $x + mb_strlen($tekst) * 5, $y + $hoyde, 842);
        $this->y[$side] = $y + $hoyde + $luft;
    }

    private function brodtekst(int $side, int $antall = 3): void
    {
        for ($i = 0; $i < $antall; $i++) {
            $this->linje($side, self::BRODTEKST, nyBlokk: $i === 0);
        }
    }

    /** @return list<string> */
    private function titler(): array
    {
        $dokument = (new Strukturtolker)->tolk($this->linjer, max(array_map(fn (Linje $l) => $l->side, $this->linjer)));

        return array_map(fn (DokumentKapittel $k) => $k->niva.' '.$k->tittel, $dokument->kapitler);
    }

    public function test_ingress_i_stor_skrift_er_tekst_og_ikke_overskrift(): void
    {
        $this->linje(3, 'Et sterkt demokrati', 30);
        $this->linje(3, 'Demokratiene i verden er under press. Vi ser at demokratier kan endres', 16, nyBlokk: true, luft: 2);
        $this->linje(3, 'til autokratier, ved at nye ledere svekker de demokratiske institusjonene.', 16, nyBlokk: false, luft: 2);
        $this->linje(3, 'Arbeiderpartiet vil fremme demokratier i verden og demokratiet i Norge.', 16, nyBlokk: false);
        $this->brodtekst(3);
        $this->linje(3, 'Trygg økonomisk styring', 16);
        $this->brodtekst(3);
        $this->linje(4, 'Et godt arbeidsliv', 30);
        $this->brodtekst(4);
        $this->linje(4, 'Sykefraværet skal ned', 16);
        $this->brodtekst(4);

        $dokument = (new Strukturtolker)->tolk($this->linjer, 4);

        $this->assertSame(['1 Et sterkt demokrati', '2 Trygg økonomisk styring', '1 Et godt arbeidsliv', '2 Sykefraværet skal ned'], $this->titlerFra($dokument->kapitler));
        $this->assertStringStartsWith('Demokratiene i verden er under press.', $dokument->kapitler[0]->avsnitt[0]->tekst);
    }

    public function test_innledning_til_liste_er_tekst_selv_i_overskriftsskrift(): void
    {
        $this->linje(3, 'Helse', 30);
        $this->linje(3, 'Sykehus', 16);
        $this->brodtekst(3);
        $this->linje(3, 'SV vil:', 16);
        $this->linje(3, '• Bygge flere sykehus.');
        $this->linje(3, '• Ansette flere sykepleiere.');
        $this->linje(3, 'Psykisk helse', 16, luft: 2);
        $this->linje(3, 'For å bedre den psykiske helsen vil SV:', 16);
        $this->linje(3, '• Ha flere psykologer.');
        $this->linje(4, 'Skole', 30);
        $this->linje(4, 'Lærere', 16);
        $this->brodtekst(4);

        $dokument = (new Strukturtolker)->tolk($this->linjer, 4);

        $this->assertSame(['1 Helse', '2 Sykehus', '2 Psykisk helse', '1 Skole', '2 Lærere'], $this->titlerFra($dokument->kapitler));
        $this->assertSame("SV vil:\n• Bygge flere sykehus.\n• Ansette flere sykepleiere.", $dokument->kapitler[1]->avsnitt[1]->tekst);
        $this->assertSame("For å bedre den psykiske helsen vil SV:\n• Ha flere psykologer.", $dokument->kapitler[2]->avsnitt[0]->tekst);
    }

    public function test_overskrift_over_to_linjer_med_komma(): void
    {
        $this->linje(3, 'Trygghet for morgendagen,', 30, luft: -3);
        $this->linje(3, 'tro på framtida', 30);
        $this->brodtekst(3);
        $this->linje(4, 'Et sterkt demokrati', 30);
        $this->brodtekst(4);

        $this->assertSame(['1 Trygghet for morgendagen, tro på framtida', '1 Et sterkt demokrati'], $this->titler());
    }

    public function test_nummererte_overskrifter_slas_ikke_sammen(): void
    {
        $this->linje(3, 'Del 1', 30);
        $this->linje(3, '1.9 Bygg landet med jernbane', 16, luft: 1);
        $this->linje(3, '1.10 Fred og internasjonal solidaritet', 16);
        $this->brodtekst(3);
        $this->linje(4, 'Del 2', 30);
        $this->brodtekst(4);

        $this->assertSame(['1 Del 1', '2 1.9 Bygg landet med jernbane', '2 1.10 Fred og internasjonal solidaritet', '1 Del 2'], $this->titler());
    }

    public function test_deler_i_mindre_skrift_enn_kapitlene_loftes_over_kapitlene(): void
    {
        $this->linje(3, 'Et sterkt demokrati', 30);
        $this->brodtekst(3);
        $this->linje(3, 'Del 1: Arbeid og økonomi', 20);
        $this->linje(3, 'Økonomi', 30);
        $this->linje(3, 'Trygg økonomisk styring', 14);
        $this->brodtekst(3);
        $this->linje(4, 'Del 2: Velferd', 20);
        $this->linje(4, 'Skole', 30);
        $this->brodtekst(4);
        $this->linje(4, 'Helse', 30);
        $this->brodtekst(4);

        $this->assertSame(
            ['1 Et sterkt demokrati', '1 Del 1: Arbeid og økonomi', '2 Økonomi', '3 Trygg økonomisk styring', '1 Del 2: Velferd', '2 Skole', '2 Helse'],
            $this->titler(),
        );
    }

    public function test_en_overskrift_i_storre_skrift_enn_de_andre_far_samme_niva(): void
    {
        $this->linje(3, 'MER FRIHET', 23);
        $this->linje(3, 'Folkeavstemninger', 15);
        $this->brodtekst(3);
        $this->linje(4, 'TRYGGHET', 23);
        $this->brodtekst(4);
        $this->linje(5, 'ØKONOMISK HANDLEFRIHET', 29);
        $this->brodtekst(5);
        $this->linje(5, 'Skatt', 15);
        $this->brodtekst(5);
        $this->linje(6, 'HELSE OG OMSORG', 23);
        $this->brodtekst(6);

        $this->assertSame(
            ['1 MER FRIHET', '2 Folkeavstemninger', '1 TRYGGHET', '1 ØKONOMISK HANDLEFRIHET', '2 Skatt', '1 HELSE OG OMSORG'],
            $this->titler(),
        );
    }

    public function test_to_spalter_som_veksler_linje_for_linje_leses_spalte_for_spalte(): void
    {
        $venstre = ['Venstre spalte linje en', 'Venstre spalte linje to', 'Venstre spalte linje tre', 'Venstre spalte linje fire', 'Venstre spalte linje fem', 'Venstre spalte linje seks'];
        $hoyre = ['Høyre spalte linje en', 'Høyre spalte linje to', 'Høyre spalte linje tre', 'Høyre spalte linje fire', 'Høyre spalte linje fem', 'Høyre spalte linje seks'];

        // Slik pdftotext leser Høyres prinsipprogram: hver linje er en egen
        // blokk, og spaltene veksler.
        $linjer = [];
        foreach ($venstre as $i => $tekst) {
            $y = 100 + $i * 14;
            $linjer[] = new Linje(1, count($linjer), $tekst, 56, $y, 250, $y + 10, 842);
            $linjer[] = new Linje(1, count($linjer), $hoyre[$i], 302, $y, 500, $y + 10, 842);
        }

        $ordnet = (new Spalteleser)->ordne($linjer);

        $this->assertSame([...$venstre, ...$hoyre], array_map(fn (Linje $l) => $l->tekst, $ordnet));
        // Linjene i hver spalte havner i samme blokk, så avsnittet holdes samlet.
        $this->assertCount(2, array_unique(array_map(fn (Linje $l) => $l->blokk, $ordnet)));
    }

    public function test_en_spalte_beholder_rekkefolgen(): void
    {
        $this->linje(3, 'Overskrift', 20);
        $this->brodtekst(3, 8);

        $this->assertSame($this->linjer, (new Spalteleser)->ordne($this->linjer));
    }

    /**
     * @param  list<DokumentKapittel>  $kapitler
     * @return list<string>
     */
    private function titlerFra(array $kapitler): array
    {
        return array_map(fn (DokumentKapittel $k) => $k->niva.' '.$k->tittel, $kapitler);
    }
}
