<?php

namespace App\Analyse;

use App\Models\Tekstbit;

final readonly class Treff
{
    public function __construct(
        public Tekstbit $bit,
        public float $likhet,
        public bool $nokkelord,
    ) {}

    public function poeng(): float
    {
        return $this->likhet + ($this->nokkelord ? 0.15 : 0.0);
    }
}
