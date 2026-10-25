<?php

namespace App\Digitalisering;

use App\Enums\Programdel;

/** En overskrift med avsnittene som står under den, før neste overskrift. */
final class DokumentKapittel
{
    /** @param list<DokumentAvsnitt> $avsnitt */
    public function __construct(
        public string $tittel,
        public int $niva,
        public ?int $side,
        public array $avsnitt = [],
        public ?Programdel $del = null,
        /** Tekst før første overskrift. Får kapittelnummer 0. */
        public bool $innledning = false,
    ) {}
}
