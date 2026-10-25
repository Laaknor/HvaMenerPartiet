<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Kjoringsstatus: string implements HasColor, HasLabel
{
    case IKo = 'i_ko';
    case Kjorer = 'kjorer';
    case Ferdig = 'ferdig';
    case Feilet = 'feilet';

    public function etikett(): string
    {
        return match ($this) {
            self::IKo => 'I kø',
            self::Kjorer => 'Kjører',
            self::Ferdig => 'Ferdig',
            self::Feilet => 'Feilet',
        };
    }

    public function farge(): string
    {
        return match ($this) {
            self::IKo => 'gray',
            self::Kjorer => 'info',
            self::Ferdig => 'success',
            self::Feilet => 'danger',
        };
    }

    public function erAktiv(): bool
    {
        return $this === self::IKo || $this === self::Kjorer;
    }

    /** @return list<self> */
    public static function aktive(): array
    {
        return [self::IKo, self::Kjorer];
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }

    public function getColor(): string
    {
        return $this->farge();
    }
}
