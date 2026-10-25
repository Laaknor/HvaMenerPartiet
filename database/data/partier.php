<?php

/*
| Partiregisteret. Vedlikeholdes manuelt. Nye partier legges til her og
| lastes inn med «php artisan db:seed --class=PartiSeeder» uten kodeendring.
| Fargene er partienes profilfarger, brukt på partikortene.
*/

return [
    ['slug' => 'ap', 'navn' => 'Arbeiderpartiet', 'forkortelse' => 'Ap', 'farge' => '#E3000F', 'nettside' => 'https://www.arbeiderpartiet.no'],
    ['slug' => 'frp', 'navn' => 'Fremskrittspartiet', 'forkortelse' => 'FrP', 'farge' => '#024C93', 'nettside' => 'https://www.frp.no'],
    ['slug' => 'h', 'navn' => 'Høyre', 'forkortelse' => 'H', 'farge' => '#0065F1', 'nettside' => 'https://hoyre.no'],
    ['slug' => 'sv', 'navn' => 'Sosialistisk Venstreparti', 'forkortelse' => 'SV', 'farge' => '#EB4040', 'nettside' => 'https://www.sv.no'],
    ['slug' => 'sp', 'navn' => 'Senterpartiet', 'forkortelse' => 'Sp', 'farge' => '#00843D', 'nettside' => 'https://www.senterpartiet.no'],
    ['slug' => 'rodt', 'navn' => 'Rødt', 'forkortelse' => 'Rødt', 'farge' => '#A50021', 'nettside' => 'https://roedt.no'],
    ['slug' => 'mdg', 'navn' => 'Miljøpartiet De Grønne', 'forkortelse' => 'MDG', 'farge' => '#439539', 'nettside' => 'https://www.mdg.no'],
    ['slug' => 'krf', 'navn' => 'Kristelig Folkeparti', 'forkortelse' => 'KrF', 'farge' => '#FFBE00', 'nettside' => 'https://krf.no'],
    ['slug' => 'v', 'navn' => 'Venstre', 'forkortelse' => 'V', 'farge' => '#00807B', 'nettside' => 'https://www.venstre.no'],
];
