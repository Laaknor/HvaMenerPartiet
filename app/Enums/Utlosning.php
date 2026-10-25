<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Hvem som startet en kjøring. */
enum Utlosning: string implements HasColor, HasLabel
{
    case Automatisk = 'automatisk';
    case Admin = 'admin';
    case Kommando = 'kommando';

    public function etikett(): string
    {
        return match ($this) {
            self::Automatisk => 'Automatisk',
            self::Admin => 'Admin',
            self::Kommando => 'Kommando',
        };
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Automatisk => 'gray',
            self::Admin => 'primary',
            self::Kommando => 'info',
        };
    }
}
