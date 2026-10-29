<?php

namespace App\Digitalisering;

/**
 * Setter linjene i riktig lesefølge på sider med to spalter.
 *
 * pdftotext leser noen sider linje for linje på tvers av spaltene, for
 * eksempel i Høyres prinsipprogram, der hver linje er en egen blokk og
 * venstre og høyre spalte veksler. Da blandes tekst fra to avsnitt sammen.
 * Andre ganger kommer slutten av høyre spalte før starten av venstre.
 *
 * Her finnes spaltemellomrommet som ingen linjer krysser. Linjer som krysser
 * det (overskrifter over hele bredden) deler siden i bånd. I hvert bånd leses
 * venstre spalte ferdig før høyre. Sider uten to tydelige spalter beholder
 * rekkefølgen fra pdftotext.
 */
class Spalteleser
{
    /** Så mange linjer må stå i hver spalte før siden regnes som tospaltet. */
    private const MIN_LINJER_PER_SPALTE = 6;

    /** Spaltemellomrom letes etter mellom 20 og 80 prosent av tekstbredden. */
    private const MIDTSONE = [0.2, 0.8];

    /** Bredden på stripen som ingen spaltelinjer kan krysse. */
    private const MELLOMROM = 6.0;

    /** Høyst så stor andel av linjene kan krysse mellomrommet (overskrifter, bilder). */
    private const MAKS_ANDEL_KRYSSER = 0.15;

    /** Andelen spaltelinjer som må starte på samme venstremarg. */
    private const MIN_ANDEL_SAMME_MARG = 0.5;

    /**
     * @param  list<Linje>  $linjer
     * @return list<Linje>
     */
    public function ordne(array $linjer): array
    {
        $sider = [];
        foreach ($linjer as $l) {
            $sider[$l->side][] = $l;
        }

        $ut = [];
        foreach ($sider as $paSiden) {
            array_push($ut, ...$this->ordneSide($paSiden));
        }

        return $ut;
    }

    /**
     * @param  list<Linje>  $linjer
     * @return list<Linje>
     */
    private function ordneSide(array $linjer): array
    {
        $ordnet = $this->ordneGruppe($linjer);

        return $ordnet === $linjer ? $linjer : $this->samleBlokker($ordnet);
    }

    /**
     * Deler linjene ved et spaltemellomrom og leser venstre del før høyre, bånd
     * for bånd. Hver del deles videre, så sider med tre spalter også leses
     * riktig. Innenfor en spalte beholdes rekkefølgen fra pdftotext.
     *
     * @param  list<Linje>  $linjer
     * @return list<Linje>
     */
    private function ordneGruppe(array $linjer): array
    {
        $skille = $this->finnSpalteskille($linjer);
        if ($skille === null) {
            return $linjer;
        }

        $tvers = array_values(array_filter($linjer, fn (Linje $l) => $l->xMin < $skille && $l->xMax > $skille));
        usort($tvers, fn (Linje $a, Linje $b) => $a->yMin <=> $b->yMin);

        $baand = [];
        foreach ($linjer as $l) {
            if ($l->xMin < $skille && $l->xMax > $skille) {
                continue;
            }
            $b = count(array_filter($tvers, fn (Linje $t) => $t->yMin < $l->yMin));
            $baand[$b][$l->xMax <= $skille ? 0 : 1][] = $l;
        }

        $ut = [];
        for ($b = 0; $b <= count($tvers); $b++) {
            array_push($ut, ...$this->ordneGruppe($baand[$b][0] ?? []), ...$this->ordneGruppe($baand[$b][1] ?? []));
            if (isset($tvers[$b])) {
                $ut[] = $tvers[$b];
            }
        }

        return $ut;
    }

    /**
     * Når en side er stokket om, gir blokkene fra pdftotext ikke lenger mening
     * (ofte er hver linje en egen blokk). Enlinjes blokker rett under linjen
     * over i samme spalte, med vanlig linjeavstand og samme venstremarg, slås
     * sammen med blokken over. Blokker med flere linjer beholdes som de er.
     *
     * @param  list<Linje>  $linjer
     * @return list<Linje>
     */
    private function samleBlokker(array $linjer): array
    {
        $storrelse = array_count_values(array_map(fn (Linje $l) => $l->blokk, $linjer));
        $ut = [];
        $forrige = null;

        foreach ($linjer as $l) {
            if ($forrige !== null
                && $storrelse[$l->blokk] === 1
                && abs($l->xMin - $forrige->xMin) < 2
                && $l->yMin > $forrige->yMin
                && ($l->yMin - $forrige->yMax) < min($l->hoyde(), $forrige->hoyde()) * 0.6
                && abs($l->hoyde() - $forrige->hoyde()) < 1) {
                $l = new Linje($l->side, $forrige->blokk, $l->tekst, $l->xMin, $l->yMin, $l->xMax, $l->yMax, $l->sideHoyde);
            }
            $ut[] = $l;
            $forrige = $l;
        }

        return $ut;
    }

    /**
     * Finner x-posisjonen til et spaltemellomrom, eller null hvis siden ikke
     * har to tydelige spalter.
     *
     * @param  list<Linje>  $linjer
     */
    private function finnSpalteskille(array $linjer): ?float
    {
        if (count($linjer) < 2 * self::MIN_LINJER_PER_SPALTE) {
            return null;
        }

        $fra = min(array_map(fn (Linje $l) => $l->xMin, $linjer));
        $til = max(array_map(fn (Linje $l) => $l->xMax, $linjer));
        $bredde = $til - $fra;

        $best = null;
        for ($x = $fra + $bredde * self::MIDTSONE[0]; $x <= $fra + $bredde * self::MIDTSONE[1]; $x += 1) {
            $krysser = 0;
            foreach ($linjer as $l) {
                if ($l->xMin < $x + self::MELLOMROM && $l->xMax > $x) {
                    $krysser++;
                }
            }
            // Like få kryssinger: velg mellomrommet nærmest midten.
            $avstand = abs($x - ($fra + $bredde / 2));
            if ($best === null || $krysser < $best['krysser'] || ($krysser === $best['krysser'] && $avstand < $best['avstand'])) {
                $best = ['x' => $x + self::MELLOMROM / 2, 'krysser' => $krysser, 'avstand' => $avstand];
            }
        }

        if ($best['krysser'] > count($linjer) * self::MAKS_ANDEL_KRYSSER) {
            return null;
        }

        $venstre = array_values(array_filter($linjer, fn (Linje $l) => $l->xMax <= $best['x']));
        $hoyre = array_values(array_filter($linjer, fn (Linje $l) => $l->xMin >= $best['x']));
        if (count($venstre) < self::MIN_LINJER_PER_SPALTE || count($hoyre) < self::MIN_LINJER_PER_SPALTE) {
            return null;
        }

        // Ekte spalter har en felles venstremarg. Det skiller dem fra korte
        // linjer som tilfeldigvis slutter før midten.
        if (! $this->harFellesMarg($venstre) || ! $this->harFellesMarg($hoyre)) {
            return null;
        }

        // Spaltene må ha brødtekst, ikke bare korte linjer som tabellceller.
        $snitt = fn (array $ls) => array_sum(array_map(fn (Linje $l) => $l->antallOrd(), $ls)) / count($ls);
        if ($snitt($venstre) < 4 || $snitt($hoyre) < 4) {
            return null;
        }

        return $best['x'];
    }

    /** @param list<Linje> $linjer */
    private function harFellesMarg(array $linjer): bool
    {
        $marger = [];
        foreach ($linjer as $l) {
            $k = (int) round($l->xMin / 3);
            $marger[$k] = ($marger[$k] ?? 0) + 1;
        }

        return max($marger) >= count($linjer) * self::MIN_ANDEL_SAMME_MARG;
    }
}
