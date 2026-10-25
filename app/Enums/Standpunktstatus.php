<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Standpunktstatus: string implements HasColor, HasLabel
{
    case Maskinell = 'maskinell';
    case Godkjent = 'godkjent';
    case Korrigert = 'korrigert';
    case Avvist = 'avvist';

    public function erGodkjent(): bool
    {
        return $this === self::Godkjent || $this === self::Korrigert;
    }

    public function etikett(): string
    {
        return match ($this) {
            self::Maskinell => 'Til gjennomgang',
            self::Godkjent => 'Godkjent',
            self::Korrigert => 'Korrigert',
            self::Avvist => 'Avvist',
        };
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Maskinell => 'warning',
            self::Godkjent => 'success',
            self::Korrigert => 'info',
            self::Avvist => 'danger',
        };
    }
}
