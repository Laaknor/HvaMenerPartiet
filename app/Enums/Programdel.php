<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Del av et kombinert dokument. Brukes bare når programtypen er kombinert. */
enum Programdel: string implements HasColor, HasLabel
{
    case Prinsipp = 'prinsipp';
    case Handling = 'handling';

    public function etikett(): string
    {
        return match ($this) {
            self::Prinsipp => 'Prinsippdel',
            self::Handling => 'Handlingsdel',
        };
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Prinsipp => 'info',
            self::Handling => 'primary',
        };
    }
}
