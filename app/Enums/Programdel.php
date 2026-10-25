<?php

namespace App\Enums;

/** Del av et kombinert dokument. Brukes bare når programtypen er kombinert. */
enum Programdel: string
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
}
