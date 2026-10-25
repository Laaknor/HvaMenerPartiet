<?php

namespace App\Enums;

/** Den faste standpunktskalaen, lik for alle temaer. */
enum Standpunktverdi: string
{
    case For = 'for';
    case DelvisFor = 'delvis_for';
    case Mot = 'mot';
    case DeltUklart = 'delt_uklart';
    case IkkeOmtalt = 'ikke_omtalt';

    public function etikett(): string
    {
        return match ($this) {
            self::For => 'For',
            self::DelvisFor => 'Delvis for',
            self::Mot => 'Mot',
            self::DeltUklart => 'Delt / uklart',
            self::IkkeOmtalt => 'Ikke omtalt',
        };
    }

    public function omtaler(): bool
    {
        return $this !== self::IkkeOmtalt;
    }

    /**
     * Retning brukes for å avgjøre om arbeidsprogram og prinsipprogram peker
     * samme vei. «For» og «Delvis for» regnes som samme retning.
     */
    public function retning(): ?string
    {
        return match ($this) {
            self::For, self::DelvisFor => 'positiv',
            self::Mot => 'negativ',
            self::DeltUklart => 'uklar',
            self::IkkeOmtalt => null,
        };
    }

    /** Rekkefølge for sortering av partikort, så for og mot står samlet. */
    public function sortering(): int
    {
        return match ($this) {
            self::For => 1,
            self::DelvisFor => 2,
            self::DeltUklart => 3,
            self::Mot => 4,
            self::IkkeOmtalt => 5,
        };
    }

    /** @return list<string> */
    public static function verdier(): array
    {
        return array_map(fn (self $v) => $v->value, self::cases());
    }
}
