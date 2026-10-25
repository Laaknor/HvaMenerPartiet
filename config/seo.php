<?php

return [

    'navn' => env('APP_NAME', 'HvaMenerPartiet'),

    // Brukes i <meta name="description">, Open Graph og llms.txt når en side ikke har egen beskrivelse.
    'beskrivelse' => 'Søk på et tema og se hva hvert parti mener, med ordrett utdrag fra partiprogrammet og lenke til kilden.',

    // Kort forklaring av metoden, øverst i llms.txt.
    'om' => 'Standpunktene er hentet fra partienes egne partiprogrammer. Hvert standpunkt er klassifisert på forhånd og kontrollert manuelt før det publiseres, og utdragene er alltid ordrette sitater med lenke til setningene i programmet. Nettsiden gjør ingen LLM-kall.',

    // Søkemotorer og LLM-er får bare indeksere siden i produksjon. Ellers gir
    // robots.txt «Disallow: /» og alle svar får X-Robots-Tag: noindex.
    'indekser' => (bool) env('SEO_INDEKSER', env('APP_ENV') === 'production'),

    // Delingsbilde for Open Graph (1200 × 630). Relativ sti eller full URL.
    'bilde' => env('SEO_BILDE', '/delingsbilde.png'),

    'locale' => 'nb_NO',

    // Stier som aldri skal indekseres, caches eller stå i sitemap (Str::is-mønstre).
    'ikke_indekser' => [
        'admin', 'admin/*',
        'horizon', 'horizon/*',
        'livewire*',
        'filament/*',
        'storage/*',
        'up',
    ],

    'kildekode' => env('SEO_KILDEKODE', 'https://github.com/Laaknor/HvaMenerPartiet'),

    // /.well-known/security.txt (RFC 9116)
    'sikkerhet' => [
        'kontakt' => [
            env('SEO_SIKKERHET_KONTAKT', 'https://github.com/Laaknor/HvaMenerPartiet/security/advisories/new'),
        ],
        'retningslinjer' => env('SEO_SIKKERHET_RETNINGSLINJER', 'https://github.com/Laaknor/HvaMenerPartiet/security/policy'),
        'sprak' => 'no, en',
        // Expires settes så mange dager frem i tid. RFC 9116 anbefaler under ett år.
        'gyldig_dager' => 180,
    ],

];
