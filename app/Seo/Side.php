<?php

namespace App\Seo;

use DateTimeInterface;

/** En offentlig side, slik den står i sitemap.xml og llms.txt. */
final readonly class Side
{
    public function __construct(
        public string $url,
        public ?string $tittel = null,
        public ?string $beskrivelse = null,
        public ?DateTimeInterface $sistEndret = null,
        /** always, hourly, daily, weekly, monthly, yearly eller never */
        public ?string $endringsfrekvens = null,
        /** 0.0–1.0 */
        public ?float $prioritet = null,
    ) {}
}
