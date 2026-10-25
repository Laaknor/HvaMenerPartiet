<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Sikkerhet: string implements HasColor, HasLabel
{
    case Hoy = 'hoy';
    case Middels = 'middels';
    case Lav = 'lav';

    public function etikett(): string
    {
        return match ($this) {
            self::Hoy => 'Høy',
            self::Middels => 'Middels',
            self::Lav => 'Lav',
        };
    }

    public function rang(): int
    {
        return match ($this) {
            self::Hoy => 3,
            self::Middels => 2,
            self::Lav => 1,
        };
    }

    /** Modellen svarer med «høy», men vi lagrer uten særnorske tegn. */
    public static function fraModell(string $verdi): self
    {
        return match (mb_strtolower(trim($verdi))) {
            'høy', 'hoy', 'high' => self::Hoy,
            'middels', 'medium' => self::Middels,
            default => self::Lav,
        };
    }

    public static function lavest(self ...$verdier): self
    {
        usort($verdier, fn (self $a, self $b) => $a->rang() <=> $b->rang());

        return $verdier[0] ?? self::Lav;
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hoy => 'success',
            self::Middels => 'warning',
            self::Lav => 'danger',
        };
    }
}
