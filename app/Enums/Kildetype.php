<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Hva et standpunkt bygger på: planen for perioden eller det langsiktige synet.
 * For kombinerte dokumenter tilsvarer handlingsdelen arbeidsprogram og
 * prinsippdelen prinsipprogram.
 */
enum Kildetype: string implements HasColor, HasLabel
{
    case Arbeidsprogram = 'arbeidsprogram';
    case Prinsipprogram = 'prinsipprogram';

    public function etikett(): string
    {
        return match ($this) {
            self::Arbeidsprogram => 'Arbeidsprogram',
            self::Prinsipprogram => 'Prinsipprogram',
        };
    }

    /** Hvilken del av et kombinert dokument kildetypen tilsvarer. */
    public function programdel(): Programdel
    {
        return match ($this) {
            self::Arbeidsprogram => Programdel::Handling,
            self::Prinsipprogram => Programdel::Prinsipp,
        };
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
