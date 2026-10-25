<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Digitaliseringsstatus: string implements HasColor, HasLabel
{
    case Registrert = 'registrert';
    case LastetNed = 'lastet_ned';
    case Digitalisert = 'digitalisert';
    case Godkjent = 'godkjent';
    case Feilet = 'feilet';

    public function etikett(): string
    {
        return match ($this) {
            self::Registrert => 'Registrert',
            self::LastetNed => 'Lastet ned',
            self::Digitalisert => 'Digitalisert, venter på kontroll',
            self::Godkjent => 'Godkjent',
            self::Feilet => 'Feilet',
        };
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Registrert => 'gray',
            self::LastetNed => 'info',
            self::Digitalisert => 'warning',
            self::Godkjent => 'success',
            self::Feilet => 'danger',
        };
    }
}
