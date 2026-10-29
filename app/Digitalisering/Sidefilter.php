<?php

namespace App\Digitalisering;

/**
 * Finner sider og linjer som ikke er programtekst: forside, kolofon,
 * innholdsfortegnelse og bakside. De tas ikke med i kapitlene, men
 * forsiden brukes fortsatt til å finne dokumenttittelen.
 */
class Sidefilter
{
    /** Sider med færre ord enn dette i starten eller slutten regnes som omslag. */
    private const MAKS_ORD_OMSLAG = 60;

    /** Sider med kolofon eller kontaktinformasjon kan ha litt mer tekst. */
    private const MAKS_ORD_KOLOFON = 200;

    /**
     * Linjer med minst så mange ord regnes som brødtekst. Et omslag har korte
     * linjer; en kort siste side med noen hele linjer er resten av et kapittel.
     */
    private const ORD_BRODTEKSTLINJE = 8;

    private const MIN_BRODTEKSTLINJER = 3;

    /** Så mange sider i hver ende undersøkes for omslag. */
    private const OMSLAGSSIDER = 2;

    /** En side med så få ord mellom to sider av innholdsfortegnelsen er et bilde med bildetekst. */
    private const MAKS_ORD_BILDESIDE = 40;

    /** Korte dokumenter har ikke omslag som må fjernes. */
    private const MIN_SIDER_FOR_OMSLAG = 4;

    private const INNHOLD_OVERSKRIFT = '/^(innhold(sfortegnelse|sliste)?|innhald(sliste)?|table of contents):?$/iu';

    /** «Forsvar ........ 23», «Forsvar 23» eller «4.2 Forsvar 23». */
    private const SLUTTER_MED_SIDETALL = '/\p{L}[\p{L}\p{P}\s]*?(\s|\.{2,}|…|·{2,})\s*\d{1,3}$/u';

    private const PRIKKLINJE = '/(\.\s?){4,}\s*\d{1,3}$|…{2,}\s*\d{1,3}$/u';

    private const BARE_SIDETALL = '/^\d{1,3}$/u';

    private const KOLOFON = '/\b(ISBN|trykk(eri)?|opplag|foto|fotografer|illustrasjon(er)?|design|layout|grafisk|utgitt av|org\.?\s?nr|postboks|telefon|tlf\.?|e-?post|www\.|https?:)/iu';

    /**
     * @param  list<Linje>  $linjer
     * @return array<int, string> sidenummer => grunn
     */
    public function utelatteSider(array $linjer): array
    {
        $sider = [];
        foreach ($linjer as $l) {
            $sider[$l->side][] = $l;
        }
        if ($sider === []) {
            return [];
        }
        ksort($sider);
        $nummer = array_keys($sider);
        $utelatt = [];

        foreach ($this->innholdsfortegnelse($sider) as $side) {
            $utelatt[$side] = 'innholdsfortegnelse';
        }

        if (count($nummer) < self::MIN_SIDER_FOR_OMSLAG) {
            return $utelatt;
        }

        // Bare sider med tekst telles, så tomme sider ikke flytter grensen.
        $forste = array_slice($nummer, 0, self::OMSLAGSSIDER);
        $siste = array_slice($nummer, -self::OMSLAGSSIDER);

        foreach ($forste as $side) {
            if (isset($utelatt[$side])) {
                continue;
            }
            if (! $this->erOmslag($sider[$side])) {
                break;
            }
            $utelatt[$side] = $side === $nummer[0] ? 'forside' : 'kolofon';
        }

        foreach (array_reverse($siste) as $side) {
            if (isset($utelatt[$side]) || count($nummer) - count($utelatt) <= 1) {
                continue;
            }
            if (! $this->erOmslag($sider[$side])) {
                break;
            }
            $utelatt[$side] = 'bakside';
        }

        ksort($utelatt);

        return $utelatt;
    }

    /** Er linjen en oppføring i en innholdsfortegnelse med prikker til sidetallet? */
    public function erPrikklinje(string $tekst): bool
    {
        return preg_match(self::PRIKKLINJE, Tekstrens::normaliser($tekst)) === 1;
    }

    /** @param list<Linje> $linjer */
    private function erOmslag(array $linjer): bool
    {
        $ord = array_sum(array_map(fn (Linje $l) => $l->antallOrd(), $linjer));
        $kolofon = count(array_filter($linjer, fn (Linje $l) => preg_match(self::KOLOFON, $l->tekst) === 1));
        if ($ord <= self::MAKS_ORD_KOLOFON && $kolofon >= 2) {
            return true;
        }

        return $ord <= self::MAKS_ORD_OMSLAG && ! $this->harBrodtekst($linjer);
    }

    /** @param list<Linje> $linjer */
    private function harBrodtekst(array $linjer): bool
    {
        $lange = count(array_filter($linjer, fn (Linje $l) => $l->antallOrd() >= self::ORD_BRODTEKSTLINJE));

        return $lange >= self::MIN_BRODTEKSTLINJER;
    }

    /**
     * En innholdsfortegnelse står tidlig i dokumentet og har mange linjer som
     * slutter med et sidetall. Den kan gå over flere sider.
     *
     * @param  array<int, list<Linje>>  $sider
     * @return list<int>
     */
    private function innholdsfortegnelse(array $sider): array
    {
        $nummer = array_keys($sider);
        $tidlig = array_slice($nummer, 0, max(6, (int) ceil(count($nummer) / 4)));
        $funnet = [];

        foreach ($tidlig as $n => $side) {
            $linjer = $sider[$side];
            $andel = $this->andelSidetall($linjer);
            $harOverskrift = (bool) array_filter($linjer, fn (Linje $l) => preg_match(self::INNHOLD_OVERSKRIFT, Tekstrens::normaliser($l->tekst)) === 1);
            $fortsetter = $funnet !== [] && $this->bareBildesiderMellom($sider, $tidlig, end($funnet), $n);

            if (($harOverskrift && $andel['antall'] >= 3)
                || ($andel['antall'] >= 5 && $andel['andel'] >= 0.5)
                || ($fortsetter && $andel['antall'] >= 3 && $andel['andel'] >= 0.35)) {
                $funnet[] = $side;
            } elseif ($funnet !== [] && ! $this->erBildeside($linjer)) {
                break;
            }
        }

        return $funnet;
    }

    /**
     * En side med bare et bilde og en kort bildetekst kan stå midt i
     * innholdsfortegnelsen, som i Arbeiderpartiets program.
     *
     * @param  list<Linje>  $linjer
     */
    private function erBildeside(array $linjer): bool
    {
        return array_sum(array_map(fn (Linje $l) => $l->antallOrd(), $linjer)) <= self::MAKS_ORD_BILDESIDE;
    }

    /**
     * @param  array<int, list<Linje>>  $sider
     * @param  list<int>  $tidlig
     */
    private function bareBildesiderMellom(array $sider, array $tidlig, int $forrige, int $n): bool
    {
        for ($i = $n - 1; $i >= 0 && $tidlig[$i] !== $forrige; $i--) {
            if (! $this->erBildeside($sider[$tidlig[$i]])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<Linje>  $linjer
     * @return array{antall: int, andel: float}
     */
    private function andelSidetall(array $linjer): array
    {
        $antall = 0;
        foreach ($linjer as $l) {
            $tekst = Tekstrens::normaliser($l->tekst);
            if (preg_match(self::SLUTTER_MED_SIDETALL, $tekst) || preg_match(self::BARE_SIDETALL, $tekst)) {
                $antall++;
            }
        }

        return ['antall' => $antall, 'andel' => $linjer ? $antall / count($linjer) : 0.0];
    }
}
