<?php

namespace App\Digitalisering;

/**
 * Regelbasert setningsdeling for norsk bokmål. Punktum i vanlige
 * forkortelser (f.eks., bl.a., jf., nr., ca.) deler ikke setninger.
 */
class Setningsdeler
{
    /** Forkortelser som nesten aldri avslutter en setning. */
    private const FORKORTELSER = [
        'f.eks', 'eks', 'bl.a', 'jf', 'jfr', 'nr', 'ca', 'dvs', 'evt', 'ev', 'pga', 'inkl', 'ekskl', 'kr', 'mill',
        'mrd', 'pst', 'ift', 'iht', 'mht', 'ifb', 'iflg', 'vedr', 'ref', 'kap', 'pkt', 'st', 'dr', 'prof', 'mnd',
        'maks', 'kl', 'tlf', 'sml', 'resp', 'gj.sn', 'f.o.m', 't.o.m', 'bl.annet', 'adm', 'avd',
        'vs', 'o', 'a', 'ifm', 'iom', 'pr', 'p', 's', 'ff', 'jan', 'feb', 'aug', 'sep',
        'sept', 'okt', 'nov', 'des',
    ];

    /** Forkortelser som ofte står sist i en setning. */
    private const KAN_AVSLUTTE = ['osv', 'mv', 'm.m', 'm.fl', 'etc', 'o.l', 'e.l', 'o.a', 'mm'];

    private const PUNKTMERKER = '•●▪■◦‣∙·–—\-\*✓□➢►▶\x{F0B7}\x{F0A7}\x{F076}\x{F0D8}\x{F0FC}';

    /**
     * Deler et avsnitt i setninger. Hver linje som begynner med et punkttegn er
     * et listepunkt, og deles videre om det inneholder flere setninger.
     *
     * @return list<array{tekst: string, er_punkt: bool}>
     */
    public function del(string $avsnitt): array
    {
        $resultat = [];

        foreach (preg_split('/\R/u', $avsnitt) as $linje) {
            $linje = Tekstrens::normaliser($linje);
            if ($linje === '') {
                continue;
            }

            $erPunkt = false;
            if (preg_match('/^['.self::PUNKTMERKER.']\s+(.*)$/u', $linje, $m)) {
                $erPunkt = true;
                $linje = trim($m[1]);
            }

            foreach ($this->delLinje($linje) as $setning) {
                $resultat[] = ['tekst' => $setning, 'er_punkt' => $erPunkt];
            }
        }

        return $resultat;
    }

    /** @return list<string> */
    public function delLinje(string $tekst): array
    {
        $setninger = [];
        $start = 0;

        preg_match_all('/[.!?…]+["»”’)\]]*(\s+)(?=\S)/u', $tekst, $treff, PREG_OFFSET_CAPTURE);

        foreach ($treff[0] as $i => [$skilletegn, $pos]) {
            $slutt = $pos + strlen(rtrim($skilletegn));
            $neste = $pos + strlen($skilletegn);

            if (! $this->erSetningsgrense(substr($tekst, $start, $slutt - $start), substr($tekst, $neste))) {
                continue;
            }

            $setninger[] = trim(substr($tekst, $start, $slutt - $start));
            $start = $neste;
        }

        $rest = trim(substr($tekst, $start));
        if ($rest !== '') {
            $setninger[] = $rest;
        }

        return array_values(array_filter($setninger, fn ($s) => $s !== ''));
    }

    private function erSetningsgrense(string $for, string $etter): bool
    {
        // Ny setning begynner ikke med liten bokstav.
        if (preg_match('/^["«»“(\[]*\p{Ll}/u', $etter)) {
            return false;
        }

        if (! preg_match('/(\S+)$/u', $for, $m)) {
            return true;
        }

        $sisteOrd = $m[1];
        if (! str_ends_with($sisteOrd, '.')) {
            return true; // ! ? …
        }

        $ord = mb_strtolower(rtrim(ltrim($sisteOrd, '("«“['), '.'));

        if (in_array($ord, self::KAN_AVSLUTTE, true)) {
            return true;
        }
        if (in_array($ord, self::FORKORTELSER, true)) {
            return false;
        }
        // Initialer («A. Hansen») og forkortelser med punktum inni («a.s.»).
        if (preg_match('/^\p{Lu}$/u', rtrim($sisteOrd, '.'))) {
            return false;
        }
        if (preg_match('/^\p{L}(\.\p{L})+$/u', $ord)) {
            return false;
        }

        return true;
    }
}
