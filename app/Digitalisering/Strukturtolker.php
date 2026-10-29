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

    /** Så mange tall på rad på én side regnes som linjenummerering. */
    private const MIN_LINJENUMRE = 5;

    private const MARG_BUNN = 0.92;

    private const PUNKTMERKE = '/^(['.Tekstrens::PUNKTTEGN.'])\s*(.*)$/u';

    /** Bokstav- eller tallpunkt med parentes: «a) …», «(b) …», «3) …». */
    private const BOKSTAVPUNKT = '/^\(?(?:[a-zæøå]|\d{1,2})\)\s+(\S.*)$/u';

    /**
     * «o …» (underpunkt) og «3. …». Kan også være vanlig tekst eller en
     * nummerert overskrift, så de regnes bare som punkt etter en avsluttet setning.
     */
    private const SVAKT_PUNKT = '/^(?:o|\d{1,2}\.)\s+(\p{Lu}.*)$/u';

    private float $brodhoyde = 10.0;

    /** @var array<string, int> høydeklynge => nivå */
    private array $nivaer = [];

    /** @var list<string> høydeklynger som bare brukes til dokumenttittel */
    private array $tittelklynger = [];

    public function __construct(
        private Sidefilter $sidefilter = new Sidefilter,
        private Spalteleser $spalteleser = new Spalteleser,
    ) {}

    /** @param list<Linje> $linjer */
    public function tolk(array $linjer, int $antallSider): Dokument
    {
        $linjer = $this->fjernLinjenumre($linjer);
        $linjer = $this->fjernToppOgBunntekst($linjer, $antallSider);
        $linjer = $this->spalteleser->ordne($linjer);
        $utelatt = $this->sidefilter->utelatteSider($linjer);
        $linjer = array_values(array_filter($linjer, fn (Linje $l) => ! $this->sidefilter->erPrikklinje($l->tekst)));

        $omslag = array_values(array_filter($linjer, fn (Linje $l) => ($utelatt[$l->side] ?? null) === 'forside'));
        $linjer = array_values(array_filter($linjer, fn (Linje $l) => ! isset($utelatt[$l->side])));

        if ($linjer === []) {
            return new Dokument(antallSider: $antallSider, utelatteSider: $utelatt);
        }

        $this->brodhoyde = $this->finnBrodhoyde($linjer);
        $linjer = $this->festPunktmerker($linjer);
        $this->finnOverskriftsnivaer($linjer);
        $overskrifter = $this->vurderOverskriftsgrupper($linjer);

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

        foreach ($linjer as $i => $linje) {
            $klynge = $this->klynge($linje->hoyde());

            // Dokumenttittel på forsiden.
            if (in_array($klynge, $this->tittelklynger, true) && $linje->side <= 2 && $this->kanVareOverskrift($linje)) {
                $tittel[] = $linje->tekst;

                continue;
            }

            if ($overskrifter[$i] ?? false) {
                $niva = $this->overskriftsniva($linje);

                // Overskrift over flere linjer.
                if ($forrigeOverskrift !== null && $avsnitt === null && $kapittel !== null && $kapittel->avsnitt === []
                    && $forrigeOverskrift->side === $linje->side
                    && $this->klynge($forrigeOverskrift->hoyde()) === $klynge
                    && ($linje->yMin - $forrigeOverskrift->yMax) < $linje->hoyde() * 0.8
                    && ! $this->erNummerertOverskrift($linje)) {
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
        $this->loftDeloverskrifter($dokument->kapitler);

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
        $punkttekst = $this->punkttekst($tekst, $avsnitt);
        $erPunkt = $punkttekst !== null;

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
     * Teksten i et listepunkt uten punktmerket, eller null hvis linjen ikke er et punkt.
     *
     * @param  array<string, mixed>|null  $avsnitt
     */
    private function punkttekst(string $tekst, ?array $avsnitt): ?string
    {
        if (preg_match(self::PUNKTMERKE, $tekst, $m) && trim($m[2]) !== '') {
            return trim($m[2]);
        }
        if (preg_match(self::BOKSTAVPUNKT, $tekst, $m)) {
            return trim($m[1]);
        }
        if (preg_match(self::SVAKT_PUNKT, $tekst, $m)) {
            $siste = $avsnitt === null ? '' : $this->sisteTekst($avsnitt);
            $etterAvsluttet = $avsnitt === null || $avsnitt['liste'] || Tekstrens::erAvsluttet($siste) || str_ends_with($siste, ':');

            return $etterAvsluttet ? trim($m[1]) : null;
        }

        return null;
    }

    /**
     * Noen PDF-er har punktmerkene («•», «a)», «3.») i en egen blokk ved siden
     * av teksten. Da festes merket til tekstlinjen som står i samme høyde, og
     * merket fjernes som egen linje. Ellers blir merkene egne avsnitt eller
     * overskrifter («a) b)»), og punktene flyter sammen til ett avsnitt.
     *
     * Tekstlinjen beholder sin egen høyde, så et merke i større skrift ikke gjør
     * den til en overskrift.
     *
     * @param  list<Linje>  $linjer
     * @return list<Linje>
     */
    private function festPunktmerker(array $linjer): array
    {
        $merker = [];
        $tekster = [];
        foreach ($linjer as $i => $l) {
            if (Tekstrens::erPunktmerke($l->tekst)) {
                $merker[$l->side][] = $i;
            } else {
                $tekster[$l->side][] = $i;
            }
        }

        if ($merker === []) {
            return $linjer;
        }

        $midt = fn (Linje $l) => ($l->yMin + $l->yMax) / 2;
        $fjern = [];
        $nye = [];

        foreach ($merker as $side => $indekser) {
            $brukt = [];
            foreach ($indekser as $mi) {
                $merke = $linjer[$mi];
                $fjern[$mi] = true;
                $best = null;
                $bestAvstand = INF;

                foreach ($tekster[$side] ?? [] as $ti) {
                    $l = $linjer[$ti];
                    $mellomrom = $l->xMin - $merke->xMax;
                    $avvik = abs($midt($l) - $midt($merke));
                    if (isset($brukt[$ti]) || $mellomrom < -1 || $mellomrom > 40
                        || $avvik > min($l->hoyde(), $merke->hoyde()) / 2) {
                        continue;
                    }
                    if ($avvik + $mellomrom / 100 < $bestAvstand) {
                        $best = $ti;
                        $bestAvstand = $avvik + $mellomrom / 100;
                    }
                }

                if ($best === null) {
                    continue; // Et merke uten tekst ved siden av er bare støy.
                }

                $brukt[$best] = true;
                $l = $nye[$best] ?? $linjer[$best];
                $merketekst = Tekstrens::normaliser($merke->tekst);
                // «3.» foran en overskrift er nummerering; ellers blir det et vanlig punkt.
                if (preg_match('/^(\d{1,2}\.|o)$/u', $merketekst) && $l->hoyde() < $this->brodhoyde * self::OVERSKRIFT_FAKTOR) {
                    $merketekst = '•';
                }

                $nye[$best] = new Linje(
                    side: $l->side,
                    blokk: $l->blokk,
                    tekst: $merketekst.' '.$l->tekst,
                    xMin: min($merke->xMin, $l->xMin),
                    yMin: $l->yMin,
                    xMax: $l->xMax,
                    yMax: $l->yMax,
                    sideHoyde: $l->sideHoyde,
                );
            }
        }

        $resultat = [];
        foreach ($linjer as $i => $l) {
            if (! isset($fjern[$i])) {
                $resultat[] = $nye[$i] ?? $l;
            }
        }

        return $resultat;
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
     * Fjerner linjenummerering i margen, som i FrPs program (1, 2, 3 … på hver
     * side). Ellers ser sidene ut som en innholdsfortegnelse med sidetall, og
     * tallene havner i teksten.
     *
     * @param  list<Linje>  $linjer
     * @return list<Linje>
     */
    private function fjernLinjenumre(array $linjer): array
    {
        $tall = [];
        foreach ($linjer as $i => $l) {
            if (preg_match('/^\d{1,3}$/u', Tekstrens::normaliser($l->tekst))) {
                $tall[$l->side][$i] = (int) Tekstrens::normaliser($l->tekst);
            }
        }

        $fjern = [];
        foreach ($tall as $paSiden) {
            if (count($paSiden) < self::MIN_LINJENUMRE) {
                continue;
            }
            $verdier = array_values($paSiden);
            $paFolge = 0;
            for ($i = 1; $i < count($verdier); $i++) {
                $verdier[$i] === $verdier[$i - 1] + 1 && $paFolge++;
            }
            if ($paFolge >= 0.8 * (count($verdier) - 1)) {
                $fjern += $paSiden;
            }
        }

        return $fjern === [] ? $linjer : array_values(array_filter($linjer, fn ($i) => ! isset($fjern[$i]), ARRAY_FILTER_USE_KEY));
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

        // En skriftstørrelse som bare brukes på én side, for eksempel én
        // overskrift satt litt større enn de andre kapitteloverskriftene, får
        // samme nivå som neste størrelse. Ellers havner resten av dokumentet
        // under den ene overskriften.
        $storrelser = array_keys($klynger);
        $niva = 1;
        foreach ($storrelser as $i => $k) {
            $neste = $storrelser[$i + 1] ?? null;
            if ($neste !== null && count($klynger[$k]['sider']) === 1 && count($klynger[$neste]['sider']) >= 3) {
                $this->nivaer[(string) $k] = min($niva, 3);

                continue;
            }
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

    /**
     * Avgjør hvilke linjer som er overskrifter, ut fra hele gruppen av linjer
     * med overskriftsskrift som står rett under hverandre.
     *
     * Skriftstørrelsen alene er ikke nok. Noen partier setter ingressen i
     * større skrift, og innledninger til lister som «SV vil:» står ofte i
     * samme skrift som underoverskriftene. Slike grupper leses som brødtekst.
     * En overskrift over flere linjer kan ha komma på slutten av en linje
     * («Trygghet for morgendagen,» + «tro på framtida»).
     *
     * @param  list<Linje>  $linjer
     * @return array<int, bool> linjeindeks => er overskrift
     */
    private function vurderOverskriftsgrupper(array $linjer): array
    {
        $svar = [];
        $gruppe = [];

        $vurder = function () use (&$gruppe, &$svar, $linjer) {
            if ($gruppe === []) {
                return;
            }
            // En innledning til en liste rett under en overskrift, som
            // «Integrering» + «SV vil:», er tekst selv om overskriften er det.
            $sisteLinje = $linjer[end($gruppe)];
            if (count($gruppe) > 1 && $this->erListeinnledning(Tekstrens::normaliser($sisteLinje->tekst))) {
                $svar[array_pop($gruppe)] = false;
            }
            $tekst = Tekstrens::normaliser(implode(' ', array_map(fn ($i) => $linjer[$i]->tekst, $gruppe)));
            $erTekst = $this->erBrodtekstIOverskriftsskrift($tekst, count($gruppe));
            $siste = end($gruppe);

            foreach ($gruppe as $i) {
                $linje = $linjer[$i];
                $kommaInni = $i !== $siste && preg_match('/,$/u', Tekstrens::normaliser($linje->tekst))
                    && $linje->antallOrd() <= self::MAKS_ORD_I_OVERSKRIFT;
                $svar[$i] = ! $erTekst && ($this->kanVareOverskrift($linje) || $kommaInni);
            }
            $gruppe = [];
        };

        foreach ($linjer as $i => $linje) {
            if (! isset($this->nivaer[$this->klynge($linje->hoyde())])) {
                $vurder();

                continue;
            }

            $forrige = $gruppe !== [] ? $linjer[end($gruppe)] : null;
            if ($forrige !== null && ! (
                $forrige->side === $linje->side
                && $this->klynge($forrige->hoyde()) === $this->klynge($linje->hoyde())
                && ($linje->yMin - $forrige->yMax) < $linje->hoyde() * 0.8
                && $linje->yMin > $forrige->yMin
                && ! $this->erNummerertOverskrift($linje)
            )) {
                $vurder();
            }
            $gruppe[] = $i;
        }
        $vurder();

        return $svar;
    }

    /**
     * Ser teksten ut som et avsnitt eller en innledning til en liste, selv om
     * den står i stor skrift?
     */
    private function erBrodtekstIOverskriftsskrift(string $tekst, int $antallLinjer): bool
    {
        $ord = count(preg_split('/\s+/u', $tekst, -1, PREG_SPLIT_NO_EMPTY));

        // Ingress: lang tekst, flere setninger, eller en hel setning med punktum.
        if ($ord > 20) {
            return true;
        }
        if ($antallLinjer > 1 && preg_match('/\p{L}[.!?]["»”’)]*\s+["«“(]*\p{Lu}/u', $tekst)) {
            return true;
        }
        if ($ord > 12 && preg_match('/[.!?]["»”’)]*$/u', $tekst)) {
            return true;
        }
        // Fortsettelsen av en setning fra forrige side eller spalte.
        if (preg_match('/^\p{Ll}/u', $tekst) && $ord > 3) {
            return true;
        }

        return $this->erListeinnledning($tekst);
    }

    /**
     * Innledning til en liste: «SV vil:», «For å få flere i arbeid vil
     * Arbeiderpartiet:». Korte overskrifter med kolon, som «Prinsipper:»,
     * regnes ikke som innledninger.
     */
    private function erListeinnledning(string $tekst): bool
    {
        $ord = count(preg_split('/\s+/u', $tekst, -1, PREG_SPLIT_NO_EMPTY));

        return preg_match('/:$/u', $tekst) === 1 && (preg_match('/\bvil( at)?:$/iu', $tekst) === 1 || $ord >= 6);
    }

    /** «4.2 Forsvar» eller «1.10 Fred og internasjonal solidaritet». */
    private function erNummerertOverskrift(Linje $l): bool
    {
        return preg_match('/^\d{1,2}(\.\d{1,2})+\.?\s+\p{Lu}/u', Tekstrens::normaliser($l->tekst)) === 1;
    }

    /**
     * Overskrifter som «Del 1: Arbeid og økonomi» deler programmet i store
     * deler, men står ofte i mindre skrift enn kapitteloverskriftene under.
     * Da løftes delene til øverste nivå, og overskriftene etter første del
     * flyttes ett nivå ned, så kapitlene havner under riktig del og ikke under kapitlet
     * foran.
     *
     * @param  list<DokumentKapittel>  $kapitler
     */
    private function loftDeloverskrifter(array $kapitler): void
    {
        $erDel = fn (DokumentKapittel $k) => preg_match('/^del\s+(\d{1,2}|[ivx]{1,4}|en|to|tre|fire|fem|seks|sju|syv|åtte)\b/iu', $k->tittel) === 1;
        $deler = array_values(array_filter($kapitler, $erDel));
        if (count($deler) < 2) {
            return;
        }

        $delniva = max(array_map(fn (DokumentKapittel $k) => $k->niva, $deler));
        $forsteDel = array_search($deler[0], $kapitler, true);
        $flyttes = array_filter(
            array_slice($kapitler, $forsteDel, null, true),
            fn (DokumentKapittel $k) => ! $erDel($k) && $k->niva <= $delniva,
        );
        if ($delniva === 1 && $flyttes === []) {
            return;
        }

        foreach ($kapitler as $i => $k) {
            if ($erDel($k)) {
                $k->niva = 1;
            } elseif ($i > $forsteDel) {
                $k->niva = min($k->niva + 1, 3);
            }
        }
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
            fn (DokumentKapittel $k) => ! preg_match('/^(innhold(sfortegnelse|sliste)?|innhald(sliste)?):?$/iu', trim($k->tittel)),
        ));
    }
}
