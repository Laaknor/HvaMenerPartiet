<?php

namespace App\Enums;

enum Programtype: string
{
    case Arbeidsprogram = 'arbeidsprogram';
    case Prinsipprogram = 'prinsipprogram';
    case Kombinert = 'kombinert';

    public function etikett(): string
    {
        return match ($this) {
            self::Arbeidsprogram => 'Arbeidsprogram',
            self::Prinsipprogram => 'Prinsipprogram',
            self::Kombinert => 'Kombinert prinsipp- og handlingsprogram',
        };
    }

    /** Kort kode brukt i faste ID-er, for eksempel «ap» i «rodt-ap2025-k7-a3-s2». */
    public function kode(): string
    {
        return match ($this) {
            self::Arbeidsprogram => 'ap',
            self::Prinsipprogram => 'pp',
            self::Kombinert => 'kp',
        };
    }

    /**
     * Hvilke kildetyper et program av denne typen gir standpunkt for.
     * Et kombinert dokument klassifiseres som om det var to programmer.
     *
     * @return list<Kildetype>
     */
    public function kildetyper(): array
    {
        return match ($this) {
            self::Arbeidsprogram => [Kildetype::Arbeidsprogram],
            self::Prinsipprogram => [Kildetype::Prinsipprogram],
            self::Kombinert => [Kildetype::Arbeidsprogram, Kildetype::Prinsipprogram],
        };
    }
}
