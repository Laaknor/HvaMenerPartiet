<?php

namespace App\Digitalisering;

/** Resultatet av strukturgjenkjenningen, før det lagres i databasen. */
final class Dokument
{
    /**
     * @param  list<DokumentKapittel>  $kapitler
     * @param  array<int, string>  $utelatteSider  sidenummer => grunn (forside, kolofon, innholdsfortegnelse, bakside)
     */
    public function __construct(
        public array $kapitler = [],
        public ?string $tittel = null,
        public int $antallSider = 0,
        public array $utelatteSider = [],
    ) {}

    /** «Side 1 (forside), 2–3 (innholdsfortegnelse)» for merknaden i admin. */
    public function utelattBeskrivelse(): ?string
    {
        if ($this->utelatteSider === []) {
            return null;
        }

        $grupper = [];
        foreach ($this->utelatteSider as $side => $grunn) {
            $siste = array_key_last($grupper);
            if ($siste !== null && $grupper[$siste]['grunn'] === $grunn && $grupper[$siste]['til'] === $side - 1) {
                $grupper[$siste]['til'] = $side;
            } else {
                $grupper[] = ['fra' => $side, 'til' => $side, 'grunn' => $grunn];
            }
        }

        $deler = array_map(fn ($g) => ($g['fra'] === $g['til'] ? $g['fra'] : "{$g['fra']}–{$g['til']}")." ({$g['grunn']})", $grupper);

        return 'Utelatt side '.implode(', ', $deler).'.';
    }

    public function antallAvsnitt(): int
    {
        return array_sum(array_map(fn (DokumentKapittel $k) => count($k->avsnitt), $this->kapitler));
    }
}
