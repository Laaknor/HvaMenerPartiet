<?php

namespace App\Digitalisering;

/** Én tekstlinje fra pdftotext -bbox-layout, med posisjon på siden. */
final readonly class Linje
{
    public function __construct(
        public int $side,
        public int $blokk,
        public string $tekst,
        public float $xMin,
        public float $yMin,
        public float $xMax,
        public float $yMax,
        public float $sideHoyde,
    ) {}

    /** Linjehøyden tilsvarer omtrent skriftstørrelsen. */
    public function hoyde(): float
    {
        return $this->yMax - $this->yMin;
    }

    public function antallOrd(): int
    {
        return count(preg_split('/\s+/u', trim($this->tekst), -1, PREG_SPLIT_NO_EMPTY));
    }
}
