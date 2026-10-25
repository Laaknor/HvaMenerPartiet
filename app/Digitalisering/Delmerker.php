<?php

namespace App\Digitalisering;

use App\Enums\Programdel;

/**
 * Merker kapitlene i et kombinert dokument som prinsippdel eller handlingsdel,
 * ut fra overskriftene.
 *
 * En hovedoverskrift uten underkapitler, som «Del 1: Prinsipprogram», er et
 * skille og gjelder til neste skille. En overskrift med egne underkapitler,
 * som «Ideologisk grunnlag» eller «Fremskrittspartiets prinsipper», gjelder
 * bare for sitt eget kapittel. Når en slik prinsippdel slutter, regnes resten
 * av dokumentet som handlingsdel. Kapitler før første markering får ingen del
 * og må merkes manuelt.
 */
class Delmerker
{
    private const PRINSIPP = '/(?<!\p{L})(prinsipp\p{L}*|prinsipi\p{L}*|grunnverdier|verdigrunnlag|våre verdier|ideologi)/iu';

    private const HANDLING = '/(?<!\p{L})(handlings(program|del)|arbeidsprogram|stortingsvalgprogram|valgprogram|politikk(en)? for (perioden|stortingsperioden)|politikken vår|vår politikk)\b/iu';

    /** @return array{merket: int, umerket: int, prinsipp: int} */
    public function merk(Dokument $dokument): array
    {
        $kapitler = array_values($dokument->kapitler);
        // Delen fra siste skille, som gjelder til neste skille.
        $gjeldende = null;
        // Delen fra et kapittel som er merket, som gjelder til kapitlet slutter.
        $kapitteldel = null;
        $kapittelniva = null;
        $merket = 0;
        $umerket = 0;
        $prinsipp = 0;

        foreach ($kapitler as $i => $kapittel) {
            if ($kapittelniva !== null && $kapittel->niva <= $kapittelniva) {
                if ($gjeldende === null && $kapitteldel === Programdel::Prinsipp) {
                    $gjeldende = Programdel::Handling;
                }
                $kapitteldel = null;
                $kapittelniva = null;
            }

            $kanSkifte = $kapitteldel === null && ($kapittel->niva === 1 || $gjeldende === null);
            $del = $kanSkifte ? $this->delFraOverskrift($kapittel->tittel) : null;
            if ($del !== null) {
                $harUnderkapitler = isset($kapitler[$i + 1]) && $kapitler[$i + 1]->niva > $kapittel->niva;
                if ($kapittel->niva === 1 && ! $harUnderkapitler) {
                    $gjeldende = $del;
                } else {
                    $kapitteldel = $del;
                    $kapittelniva = $kapittel->niva;
                }
            }

            $kapittel->del = $kapitteldel ?? $gjeldende;
            $kapittel->del === null ? $umerket++ : $merket++;
            $kapittel->del === Programdel::Prinsipp && $prinsipp++;
        }

        return ['merket' => $merket, 'umerket' => $umerket, 'prinsipp' => $prinsipp];
    }

    public function delFraOverskrift(string $tittel): ?Programdel
    {
        // En overskrift med begge ord, som «Prinsipp- og handlingsprogram», er
        // tittelen på hele dokumentet og er ikke et skifte mellom delene.
        $prinsipp = preg_match(self::PRINSIPP, $tittel) === 1;
        $handling = preg_match(self::HANDLING, $tittel) === 1;

        if ($prinsipp && $handling) {
            return null;
        }
        if ($handling) {
            return Programdel::Handling;
        }
        if ($prinsipp) {
            return Programdel::Prinsipp;
        }

        return null;
    }
}
