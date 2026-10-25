<?php

return [

    // Offentlige sider lagres ferdig rendret i cachen. Av som standard lokalt,
    // så endringer i Blade vises med en gang under utvikling.
    'aktiv' => (bool) env('SIDECACHE', env('APP_ENV') !== 'local'),

    // Cache-store (standard: CACHE_STORE, som er Redis i produksjon).
    'store' => env('SIDECACHE_STORE'),

    // Levetid i sekunder (24 timer).
    'levetid' => (int) env('SIDECACHE_LEVETID', 86400),

    // Identifiserer deployet versjon. Settes den (f.eks. til git-commit i
    // deployskriptet), tømmes cachen når verdien endres. Uten verdi brukes
    // app-mappen, git-commit og tidspunktet for siste byggede ressurser.
    'deployment' => env('APP_DEPLOYMENT'),

    // Parametere som ikke endrer innholdet og derfor ikke gir egen cache-nøkkel.
    'ignorer_parametere' => ['utm_*', 'mtm_*', 'pk_*', 'fbclid', 'gclid', 'msclkid', '_ga'],

];
