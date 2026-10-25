<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Temastatus: string implements HasColor, HasLabel
{
    case Foreslatt = 'foreslatt';
    case Godkjent = 'godkjent';
    case Analysert = 'analysert';
    case Publisert = 'publisert';

    public function etikett(): string
    {
        return match ($this) {
            self::Foreslatt => 'Foreslått',
            self::Godkjent => 'Godkjent',
            self::Analysert => 'Analysert',
            self::Publisert => 'Publisert',
        };
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Foreslatt => 'gray',
            self::Godkjent => 'info',
            self::Analysert => 'warning',
            self::Publisert => 'success',
        };
    }
}
