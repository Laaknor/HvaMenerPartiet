<?php

namespace App\Analyse;

use App\Enums\Kildetype;
use App\Enums\Standpunktverdi;
use App\Models\Standpunkt;

/**
 * Fast regel for hvordan arbeidsprogram og prinsipprogram vises sammen.
 * Dette er bevisst vanlig kode og ikke en LLM-vurdering, slik at den er lett
 * å forklare på metodesiden.
 *
 * | Arbeidsprogram | Prinsipprogram                 | Visning                                         |
 * |----------------|--------------------------------|-------------------------------------------------|
 * | Omtaler        | Samme retning eller ikke omtalt| Merkelapp fra arbeidsprogrammet                 |
 * | Ikke omtalt    | Omtaler                        | Merkelapp fra prinsipprogrammet, tydelig merket |
 * | Omtaler        | Motsatt eller annen retning    | Begge merkelapper, med merknad om avvik         |
 * | Ikke omtalt    | Ikke omtalt                    | «Ikke omtalt»                                   |
 */
class Visningsregel
{
    public const MERKNAD_AVVIK = 'Langsiktig mål avviker fra planen for perioden';

    public const MERKNAD_FRA_PRINSIPP = 'Fra prinsipprogrammet';

    public function vis(?Standpunkt $arbeid, ?Standpunkt $prinsipp, bool $harPrinsipprogram): Partivisning
    {
        $av = $arbeid?->verdi ?? Standpunktverdi::IkkeOmtalt;
        $pv = $harPrinsipprogram ? ($prinsipp?->verdi ?? Standpunktverdi::IkkeOmtalt) : Standpunktverdi::IkkeOmtalt;
        $prinsipp = $harPrinsipprogram ? $prinsipp : null;

        if ($av->omtaler()) {
            $avvik = $pv->omtaler() && $pv->retning() !== $av->retning();

            return new Partivisning(
                hovedverdi: $av,
                hovedkilde: Kildetype::Arbeidsprogram,
                hovedstandpunkt: $arbeid,
                prinsippstandpunkt: $prinsipp,
                harPrinsipprogram: $harPrinsipprogram,
                avvik: $avvik,
                merknad: $avvik ? self::MERKNAD_AVVIK : null,
            );
        }

        if ($pv->omtaler()) {
            return new Partivisning(
                hovedverdi: $pv,
                hovedkilde: Kildetype::Prinsipprogram,
                hovedstandpunkt: $prinsipp,
                prinsippstandpunkt: $prinsipp,
                harPrinsipprogram: true,
                avvik: false,
                merknad: self::MERKNAD_FRA_PRINSIPP,
            );
        }

        return new Partivisning(
            hovedverdi: Standpunktverdi::IkkeOmtalt,
            hovedkilde: null,
            hovedstandpunkt: $arbeid,
            prinsippstandpunkt: $prinsipp,
            harPrinsipprogram: $harPrinsipprogram,
            avvik: false,
            merknad: null,
        );
    }
}
