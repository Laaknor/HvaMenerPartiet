<?php

namespace App\Analyse;

use App\Enums\Kildetype;
use App\Enums\Standpunktverdi;
use App\Models\Standpunkt;

/** Hva partikortet viser for ett parti og ett tema. */
final readonly class Partivisning
{
    public function __construct(
        /** Hovedmerkelappen på kortet. */
        public Standpunktverdi $hovedverdi,
        /** Programmet hovedmerkelappen kommer fra, eller null når ingen av dem omtaler temaet. */
        public ?Kildetype $hovedkilde,
        /** Standpunktet merkelappen og hovedutdraget bygger på. */
        public ?Standpunkt $hovedstandpunkt,
        /** Linjen «Prinsipielt syn». Null når partiet ikke har prinsipprogram. */
        public ?Standpunkt $prinsippstandpunkt,
        public bool $harPrinsipprogram,
        /** Vis begge merkelapper side om side. */
        public bool $avvik,
        public ?string $merknad,
    ) {}

    public function prinsipielleSyn(): string
    {
        if (! $this->harPrinsipprogram) {
            return 'Ingen prinsipprogram';
        }

        return $this->prinsippstandpunkt?->verdi->etikett() ?? Standpunktverdi::IkkeOmtalt->etikett();
    }
}
