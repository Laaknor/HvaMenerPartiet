<?php

namespace App\Digitalisering;

use App\Enums\Programdel;

/**
 * Merker kapitlene i et kombinert dokument som prinsippdel eller handlingsdel,
 * ut fra overskriftene. Et kapittel arver delen fra den siste overskriften
 * som markerte et skifte. Kapitler før første markering får ingen del og må
 * merkes manuelt.
 */
class Delmerker
{
    private const PRINSIPP = '/(?<!\p{L})(prinsipp\p{L}*|prinsipi\p{L}*|grunnverdier|verdigrunnlag|våre verdier|ideologi)/iu';

    private const HANDLING = '/(?<!\p{L})(handlings(program|del)|arbeidsprogram|stortingsvalgprogram|valgprogram|politikk(en)? for (perioden|stortingsperioden)|politikken vår|vår politikk)\b/iu';

    /** @return array{merket: int, umerket: int, prinsipp: int} */
    public function merk(Dokument $dokument): array
    {
        $gjeldende = null;
        $merket = 0;
        $umerket = 0;
        $prinsipp = 0;

        foreach ($dokument->kapitler as $kapittel) {
            if ($kapittel->niva === 1 || $gjeldende === null) {
                $del = $this->delFraOverskrift($kapittel->tittel);
                if ($del !== null) {
                    $gjeldende = $del;
                }
            }

            $kapittel->del = $gjeldende;
            $gjeldende === null ? $umerket++ : $merket++;
            $gjeldende === Programdel::Prinsipp && $prinsipp++;
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
