<?php

namespace App\Enums;

enum Standpunktstatus: string
{
    case Maskinell = 'maskinell';
    case Godkjent = 'godkjent';
    case Korrigert = 'korrigert';
    case Avvist = 'avvist';

    public function erGodkjent(): bool
    {
        return $this === self::Godkjent || $this === self::Korrigert;
    }
}
