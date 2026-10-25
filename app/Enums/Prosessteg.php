<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Stegene i flyten et program går gjennom før standpunktene kan kontrolleres. */
enum Prosessteg: string implements HasColor, HasLabel
{
    case Innlesing = 'innlesing';
    case Digitalisering = 'digitalisering';
    case Embeddings = 'embeddings';
    case Klassifisering = 'klassifisering';

    public function etikett(): string
    {
        return match ($this) {
            self::Innlesing => 'Innlesing fra docs/',
            self::Digitalisering => 'Digitalisering og oppsplitting',
            self::Embeddings => 'Embeddings',
            self::Klassifisering => 'Klassifisering',
        };
    }

    /** Hvilken kapasitet steget bruker: import-prosessene eller Ollama-serveren. */
    public function kapasitetsgruppe(): string
    {
        return match ($this) {
            self::Innlesing, self::Digitalisering => 'import',
            self::Embeddings, self::Klassifisering => 'ollama',
        };
    }

    /** @return list<self> */
    public static function iGruppe(string $gruppe): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->kapasitetsgruppe() === $gruppe));
    }

    public function getLabel(): string
    {
        return $this->etikett();
    }

    public function getColor(): string
    {
        return 'primary';
    }
}
