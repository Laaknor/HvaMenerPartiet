<?php

namespace App\Enums;

enum Digitaliseringsstatus: string
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
}
