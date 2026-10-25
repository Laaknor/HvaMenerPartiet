<?php

namespace App\Digitalisering;

final class Lagringsresultat
{
    public function __construct(
        public int $kapitler = 0,
        public int $avsnitt = 0,
        public int $setninger = 0,
        /** Setninger som beholdt sin faste ID fra forrige import. */
        public int $beholdt = 0,
        public int $nye = 0,
        public int $fjernet = 0,
        /** Standpunkter som pekte på fjernede setninger og må vurderes på nytt. */
        public int $berorteStandpunkter = 0,
    ) {}
}
