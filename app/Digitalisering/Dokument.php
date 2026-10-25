<?php

namespace App\Digitalisering;

/** Resultatet av strukturgjenkjenningen, før det lagres i databasen. */
final class Dokument
{
    /** @param list<DokumentKapittel> $kapitler */
    public function __construct(
        public array $kapitler = [],
        public ?string $tittel = null,
        public int $antallSider = 0,
    ) {}

    public function antallAvsnitt(): int
    {
        return array_sum(array_map(fn (DokumentKapittel $k) => count($k->avsnitt), $this->kapitler));
    }
}
