<?php

namespace App\Enums;

enum Sikkerhet: string
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
}
