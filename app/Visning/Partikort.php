<?php

namespace App\Visning;

use App\Enums\Sikkerhet;
use App\Enums\Standpunktverdi;
use App\Models\Parti;

/** Ett parti på en temaside: merkelapp, utdrag og prinsipielt syn. */
final readonly class Partikort
{
    public function __construct(
        public Parti $parti,
        /** Hovedmerkelappen. Null betyr «Ingen prinsipprogram» (bare i prinsippvisningen). */
        public ?Standpunktverdi $verdi,
        public ?Kildeutdrag $kilde,
        public ?Sikkerhet $sikkerhet,
        /** «Fra prinsipprogrammet» eller merknaden om avvik. */
        public ?string $merknad,
        /** Prinsipprogrammets merkelapp når den avviker fra arbeidsprogrammet. */
        public ?Standpunktverdi $avvikverdi,
        /** Linjen «Prinsipielt syn», eller null når den ikke skal vises. */
        public ?string $prinsipieltSyn,
        public ?Kildeutdrag $prinsippkilde,
    ) {}

    public function etikett(): string
    {
        return $this->verdi?->etikett() ?? 'Ingen prinsipprogram';
    }

    /** CSS-modifikator for merkelappen. */
    public function merkelappklasse(?Standpunktverdi $verdi = null): string
    {
        $verdi ??= $this->verdi;

        return 'merkelapp--'.str_replace('_', '-', $verdi?->value ?? 'ingen');
    }

    public function sortering(): int
    {
        return $this->verdi?->sortering() ?? 6;
    }
}
