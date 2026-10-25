<?php

namespace App\Digitalisering;

/**
 * Et avsnitt. En punktliste er ett avsnitt der hvert punkt står på egen
 * linje og begynner med «• », eventuelt etter en innledende setning.
 */
final class DokumentAvsnitt
{
    public function __construct(
        public string $tekst,
        public int $side,
        public int $tilSide,
        public bool $erListe = false,
    ) {}
}
