<?php

namespace App\Digitalisering;

/**
 * Gjør linjer med posisjon om til kapitler og avsnitt.
 *
 * Overskrifter gjenkjennes på linjehøyde (som følger skriftstørrelsen) og
 * nummerering. Topptekst, bunntekst og sidetall fjernes, orddeling over
 * linjeskift slås sammen, og avsnitt som fortsetter på neste side eller
 * spalte settes sammen igjen. Forside, kolofon, innholdsfortegnelse, bakside
 * og henvisninger til sidetall tas ikke med.
 */
class Strukturtolker
{
    /** Linjer så mye høyere enn brødteksten regnes som mulige overskrifter. */
    private const OVERSKRIFT_FAKTOR = 1.12;

    private const MAKS_ORD_I_OVERSKRIFT = 18;

    /** Andel av sidehøyden øverst og nederst der topptekst og bunntekst står. */
    private const MARG_TOPP = 0.08;

    private const MARG_BUNN = 0.92;

    private const PUNKTMERKE = '/^([•●▪■◦‣∙·–—\-\*✓□➢►▶\x{F0B7}\x{F0A7}\x{F076}\x{F0D8}\x{F0FC}])\s*(.*)$/u';

    private float $brodhoyde = 10.0;

    /** @var array<string, int> høydeklynge => nivå */
    private array $nivaer = [];

    /** @var list<string> høydeklynger som bare brukes til dokumenttittel */
    private array $tittelklynger = [];

    public function __construct(private Sidefilter $sidefilter = new Sidefilter) {}

    /** @param list<Linje> $linjer */
    public function tolk(array $linjer, int $antallSider): Dokument
    {
        $linjer = $this->fjernToppOgBunntekst($linjer, $antallSider);
        $utelatt = $this->sidefilter->utelatteSider($linjer);
        $linjer = array_values(array_filter($linjer, fn (Linje $l) => ! $this->sidefilter->erPrikklinje($l->tekst)));

        $omslag = array_values(array_filter($linjer, fn (Linje $l) => ($utelatt[$l->side] ?? null) === 'forside'));
        $linjer = array_values(array_filter($linjer, fn (Linje $l) => ! isset($utelatt[$l->side])));

        if ($linjer === []) {
            return new Dokument(antallSider: $antallSider, utelatteSider: $utelatt);
        }

        $this->brodhoyde = $this->finnBrodhoyde($linjer);
        $this->finnOverskriftsnivaer($linjer);

        $dokument = new Dokument(antallSider: $antallSider, utelatteSider: $utelatt);
        $tittel = $this->tittelFraForside($omslag);

        /** @var DokumentKapittel|null $kapittel */
        $kapittel = null;
        $avsnitt = null; // array{linjer: list<string>, side: int, tilSide: int, liste: bool, punkter: list<array{tekst:string,x:float}>, sisteLinje: Linje}
        $forrigeOverskrift = null;

        $lukkAvsnitt = function () use (&$avsnitt, &$kapittel, $dokument) {
            if ($avsnitt === null) {
                return;
            }
            $tekst = $this->avsnittstekst($avsnitt);
            if ($tekst !== '') {
                if ($kapittel === null) {
                    $kapittel = new DokumentKapittel('Innledning', 1, $avsnitt['side'], innledning: true);
                    $dokument->kapitler[] = $kapittel;
                }
                $kapittel->avsnitt[] = new DokumentAvsnitt($tekst, $avsnitt['side'], $avsnitt['tilSide'], $avsnitt['liste']);
            }
            $avsnitt = null;
        };

        foreach ($linjer as $linje) {
            $klynge = $this->klynge($linje->hoyde());

            // Dokumenttittel på forsiden.
            if (in_array($klynge, $this->tittelklynger, true) && $linje->side <= 2 && $this->kanVareOverskrift($linje)) {
                $tittel[] = $linje->tekst;

                continue;
            }

            if ($this->erOverskrift($linje)) {
                $niva = $this->overskriftsniva($linje);

                // Overskrift over flere linjer.
                if ($forrigeOverskrift !== null && $avsnitt === null && $kapittel !== null && $kapittel->avsnitt === []
                    && $forrigeOverskrift->side === $linje->side
                    && $this->klynge($forrigeOverskrift->hoyde()) === $klynge
                    && ($linje->yMin - $forrigeOverskrift->yMax) < $linje->hoyde() * 0.8) {
                    $kapittel->tittel = Tekstrens::skjot($kapittel->tittel, $linje->tekst);
                    $forrigeOverskrift = $linje;

                    continue;
                }

                $lukkAvsnitt();
                $kapittel = new DokumentKapittel(Tekstrens::normaliser($linje->tekst), $niva, $linje->side);
                $dokument->kapitler[] = $kapittel;
                $forrigeOverskrift = $linje;

                continue;
            }

            $forrigeOverskrift = null;
            $this->leggTilLinje($linje, $avsnitt, $lukkAvsnitt);
        }

        $lukkAvsnitt();

        $dokument->tittel = $tittel ? Tekstrens::normaliser(implode(' ', $tittel)) : null;
        $dokument->kapitler = $this->fjernInnholdsfortegnelse($dokument->kapitler);

        return $dokument;
    }

    /**
     * Tittelen på forsiden er linjene med størst skrift.
     *
     * @param  list<Linje>  $linjer
     * @return list<string>
     */
    private function tittelFraForside(array $linjer): array
    {
        $kandidater = array_values(array_filter($linjer, fn (Linje $l) => $this->kanVareOverskrift($l)));
        if ($kandidater === []) {
            return [];
        }

        $storst = max(array_map(fn (Linje $l) => (float) $this->klynge($l->hoyde()), $kandidater));

        return array_values(array_map(
            fn (Linje $l) => $l->tekst,
            array_filter($kandidater, fn (Linje $l) => (float) $this->klynge($l->hoyde()) === $storst),
        ));
    }

    /**
     * @param  array<string, mixed>|null  $avsnitt
     */
    private function leggTilLinje(Linje $linje, ?array &$avsnitt, callable $lukkAvsnitt): void
    {
        $tekst = Tekstrens::normaliser($linje->tekst);
        $erPunkt = preg_match(self::PUNKTMERKE, $tekst, $m) === 1 && trim($m[2]) !== '';
        $punkttekst = $erPunkt ? trim($m[2]) : null;

        if ($avsnitt === null) {
            $avsnitt = $this->nyttAvsnitt($linje, $erPunkt ? null : $tekst);
            if ($erPunkt) {
                $avsnitt['liste'] = true;
                $avsnitt['punkter'][] = ['tekst' => $punkttekst, 'x' => $linje->xMin];
            }

            return;
        }

        $forrige = $avsnitt['sisteLinje'];
        $sisteTekst = $this->sisteTekst($avsnitt);

        if ($erPunkt) {
            // Et punkt hører til listen vi er i, eller til en innledning som slutter med kolon.
            if ($avsnitt['liste'] || str_ends_with($sisteTekst, ':')) {
                $avsnitt['liste'] = true;
                $avsnitt['punkter'][] = ['tekst' => $punkttekst, 'x' => $linje->xMin];
                $this->oppdaterSlutt($avsnitt, $linje);

                return;
            }

            $lukkAvsnitt();
            $avsnitt = $this->nyttAvsnitt($linje, null);
            $avsnitt['liste'] = true;
            $avsnitt['punkter'][] = ['tekst' => $punkttekst, 'x' => $linje->xMin];

            return;
        }

        if ($this->fortsetter($forrige, $linje, $sisteTekst, $avsnitt)) {
            if ($avsnitt['punkter'] !== []) {
                $i = array_key_last($avsnitt['punkter']);
                $avsnitt['punkter'][$i]['tekst'] = Tekstrens::skjot($avsnitt['punkter'][$i]['tekst'], $tekst);
            } else {
                $i = array_key_last($avsnitt['linjer']);
                $avsnitt['linjer'][$i] = Tekstrens::skjot($avsnitt['linjer'][$i], $tekst);
            }
            $this->oppdaterSlutt($avsnitt, $linje);

            return;
        }

        $lukkAvsnitt();
        $avsnitt = $this->nyttAvsnitt($linje, $tekst);
    }

    /**
     * Hører linjen til samme avsnitt (eller samme listepunkt) som forrige linje?
     *
     * @param  array<string, mixed>  $avsnitt
     */
    private function fortsetter(Linje $forrige, Linje $linje, string $sisteTekst, array $avsnitt): bool
    {
        $avsluttet = Tekstrens::erAvsluttet($sisteTekst);
        $smaBokstav = Tekstrens::begynnerMedSmaBokstav($linje->tekst);
        $orddelt = (bool) preg_match('/\p{L}[-\x{00AD}]$/u', $sisteTekst);

        // Ny side eller ny blokk: bare fortsettelse når setningen tydelig ikke er ferdig.
        if ($linje->side !== $forrige->side || $linje->blokk !== $forrige->blokk) {
            return ! $avsluttet && ($smaBokstav || $orddelt);
        }

        // Stor luft mellom linjene betyr nytt avsnitt.
        if (($linje->yMin - $forrige->yMax) > $this->brodhoyde * 0.9) {
            return ! $avsluttet && $smaBokstav;
        }

        // I en liste: en linje uten punkttegn fortsetter punktet hvis den er
        // innrykket eller punktet ikke er ferdig.
        if ($avsnitt['liste'] && $avsnitt['punkter'] !== []) {
            $punkt = end($avsnitt['punkter']);

            return $smaBokstav || ! $avsluttet || $linje->xMin > $punkt['x'] + 2;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function nyttAvsnitt(Linje $linje, ?string $tekst): array
    {
        return [
            'linjer' => $tekst !== null ? [$tekst] : [],
            'punkter' => [],
            'liste' => false,
            'side' => $linje->side,
            'tilSide' => $linje->side,
            'sisteLinje' => $linje,
        ];
    }

    /** @param array<string, mixed> $avsnitt */
    private function oppdaterSlutt(array &$avsnitt, Linje $linje): void
    {
        $avsnitt['tilSide'] = $linje->side;
        $avsnitt['sisteLinje'] = $linje;
    }

    /** @param array<string, mixed> $avsnitt */
    private function sisteTekst(array $avsnitt): string
    {
        if ($avsnitt['punkter'] !== []) {
            return end($avsnitt['punkter'])['tekst'];
        }

        return $avsnitt['linjer'] !== [] ? end($avsnitt['linjer']) : '';
    }

    /** @param array<string, mixed> $avsnitt */
    private function avsnittstekst(array $avsnitt): string
    {
        $deler = array_map(fn ($l) => Tekstrens::normaliser(str_replace("\u{00AD}", '', $l)), $avsnitt['linjer']);
        foreach ($avsnitt['punkter'] as $punkt) {
            $deler[] = '• '.Tekstrens::normaliser(str_replace("\u{00AD}", '', $punkt['tekst']));
        }

        $deler = array_map(fn ($d) => Tekstrens::fjernSidehenvisninger($d), $deler);

        return trim(implode("\n", array_filter($deler, fn ($d) => $d !== '' && $d !== '•')));
    }

    /**
     * Fjerner sidetall og linjer som gjentas i margen på mange sider.
     *
     * @param  list<Linje>  $linjer
     * @return list<Linje>
     */
    private function fjernToppOgBunntekst(array $linjer, int $antallSider): array
    {
        $iMargen = fn (Linje $l) => $l->yMax < $l->sideHoyde * self::MARG_TOPP || $l->yMin > $l->sideHoyde * self::MARG_BUNN;
        $nokkel = fn (Linje $l) => preg_replace('/\d+/u', '#', mb_strtolower(Tekstrens::normaliser($l->tekst)));

        $sider = [];
        foreach ($linjer as $l) {
            if ($iMargen($l)) {
                $sider[$nokkel($l)][$l->side] = true;
            }
        }

        $grense = max(2, (int) ceil($antallSider * 0.25));

        return array_values(array_filter($linjer, function (Linje $l) use ($iMargen, $nokkel, $sider, $grense) {
            $tekst = Tekstrens::normaliser($l->tekst);

            if (preg_match('/^(side\s+)?\d{1,4}(\s*(av|\/)\s*\d{1,4})?$/iu', $tekst)) {
                return ! $iMargen($l);
            }

            if ($iMargen($l) && count($sider[$nokkel($l)] ?? []) >= $grense) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Brødteksthøyden er den linjehøyden som dekker flest tegn.
     *
     * @param  list<Linje>  $linjer
     */
    private function finnBrodhoyde(array $linjer): float
    {
        $tegn = [];
        foreach ($linjer as $l) {
            $k = $this->klynge($l->hoyde());
            $tegn[$k] = ($tegn[$k] ?? 0) + mb_strlen($l->tekst);
        }
        arsort($tegn);

        return (float) array_key_first($tegn);
    }

    /** @param list<Linje> $linjer */
    private function finnOverskriftsnivaer(array $linjer): void
    {
        $klynger = [];
        foreach ($linjer as $l) {
            if ($this->kanVareOverskrift($l) && $l->hoyde() >= $this->brodhoyde * self::OVERSKRIFT_FAKTOR) {
                $k = $this->klynge($l->hoyde());
                $klynger[$k]['sider'][$l->side] = true;
                $klynger[$k]['antall'] = ($klynger[$k]['antall'] ?? 0) + 1;
            }
        }

        uksort($klynger, fn ($a, $b) => (float) $b <=> (float) $a);

        $this->tittelklynger = [];
        $this->nivaer = [];

        // Den største skriften brukes bare til tittelen hvis den kun finnes på forsiden.
        $storste = array_key_first($klynger);
        if ($storste !== null && count($klynger) > 1 && max(array_keys($klynger[$storste]['sider'])) <= 2) {
            $this->tittelklynger[] = (string) $storste;
            unset($klynger[$storste]);
        }

        $niva = 1;
        foreach (array_keys($klynger) as $k) {
            $this->nivaer[(string) $k] = min($niva++, 3);
        }
    }

    private function kanVareOverskrift(Linje $l): bool
    {
        $tekst = Tekstrens::normaliser($l->tekst);

        return $l->antallOrd() <= self::MAKS_ORD_I_OVERSKRIFT
            && mb_strlen($tekst) <= 160
            && ! preg_match('/[,;]$/u', $tekst)
            && preg_match('/\p{L}/u', $tekst)
            && ! preg_match(self::PUNKTMERKE, $tekst);
    }

    private function erOverskrift(Linje $l): bool
    {
        return $this->kanVareOverskrift($l) && isset($this->nivaer[$this->klynge($l->hoyde())]);
    }

    private function overskriftsniva(Linje $l): int
    {
        // Nummerering («4.2 Forsvar») går foran skriftstørrelse.
        if (preg_match('/^(\d{1,2}(?:\.\d{1,2})*)\.?\s+\p{L}/u', Tekstrens::normaliser($l->tekst), $m)) {
            return min(substr_count($m[1], '.') + 1, 3);
        }

        return $this->nivaer[$this->klynge($l->hoyde())] ?? 1;
    }

    /** Runder høyden av så små forskjeller ikke gir egne nivåer. */
    private function klynge(float $hoyde): string
    {
        return number_format(round($hoyde * 2) / 2, 1, '.', '');
    }

    /**
     * @param  list<DokumentKapittel>  $kapitler
     * @return list<DokumentKapittel>
     */
    private function fjernInnholdsfortegnelse(array $kapitler): array
    {
        return array_values(array_filter(
            $kapitler,
            fn (DokumentKapittel $k) => ! preg_match('/^(innhold(sfortegnelse|sliste)?|innhald(sliste)?)$/iu', trim($k->tittel)),
        ));
    }
}
