<?php

/*
| Temaene for prototypen i fase 1. Hvert tema har en nøytralt formulert
| påstand som standpunktet måles mot, og synonymer for nøkkelordsøk.
| «forvekslinger» er nærliggende begreper som ikke er temaet. Avsnitt som
| bare treffer dem, sendes ikke til modellen, og modellen får beskjed om at
| de ikke tar stilling til påstanden.
| Senere foreslås temaene av LLM-en og godkjennes i admin.
*/

return [
    [
        'slug' => 'nato',
        'navn' => 'NATO',
        'beskrivelse' => 'Norges medlemskap i forsvarsalliansen NATO.',
        'pastand' => 'Norge bør være medlem av NATO.',
        'synonymer' => ['NATO', 'forsvarsallianse', 'atlanterhavspakten', 'forsvarsalliansen', 'allierte', 'alliansen'],
    ],
    [
        'slug' => 'eu',
        'navn' => 'EU-medlemskap',
        'beskrivelse' => 'Om Norge bør bli medlem av Den europeiske union.',
        'pastand' => 'Norge bør bli medlem av EU.',
        'synonymer' => ['EU', 'Den europeiske union', 'EU-medlemskap', 'europeisk union'],
    ],
    [
        'slug' => 'eos',
        'navn' => 'EØS-avtalen',
        'beskrivelse' => 'Avtalen om Det europeiske økonomiske samarbeidsområdet.',
        'pastand' => 'Norge bør beholde EØS-avtalen.',
        'synonymer' => ['EØS', 'EØS-avtalen', 'det indre marked', 'indre marked', 'handelsavtale med EU'],
    ],
    [
        'slug' => 'eiendomsskatt',
        'navn' => 'Eiendomsskatt',
        'beskrivelse' => 'Kommunal skatt på bolig og annen fast eiendom.',
        'pastand' => 'Kommunene bør kunne kreve eiendomsskatt på bolig.',
        'synonymer' => ['eiendomsskatt', 'eiendomsskatten', 'boligskatt', 'skatt på bolig'],
        'forvekslinger' => ['formuesskatt', 'formuesbeskatning', 'skatt på formue', 'eierbeskatning', 'eierskatt', 'arveavgift', 'utbytteskatt', 'skatt på utbytte'],
    ],
    [
        'slug' => 'formuesskatt',
        'navn' => 'Formuesskatt',
        'beskrivelse' => 'Skatt på formue over bunnfradraget.',
        'pastand' => 'Formuesskatten bør reduseres eller fjernes.',
        'synonymer' => ['formuesskatt', 'formuesskatten', 'formuesbeskatning', 'skatt på formue', 'arbeidende kapital', 'eierbeskatning'],
        'forvekslinger' => ['eiendomsskatt', 'boligskatt'],
    ],
    [
        'slug' => 'oljeleting',
        'navn' => 'Leting etter olje og gass',
        'beskrivelse' => 'Leting etter nye olje- og gassforekomster på norsk sokkel.',
        'pastand' => 'Norge bør fortsette å lete etter ny olje og gass.',
        'synonymer' => ['oljeleting', 'petroleum', 'olje og gass', 'oljevirksomhet', 'sokkel', 'konsesjonsrunde', 'tildeling i forhåndsdefinerte områder', 'TFO'],
    ],
    [
        'slug' => 'atomkraft',
        'navn' => 'Atomkraft',
        'beskrivelse' => 'Kjernekraft som energikilde i Norge.',
        'pastand' => 'Norge bør åpne for å bygge atomkraftverk.',
        'synonymer' => ['atomkraft', 'kjernekraft', 'kjernekraftverk', 'atomkraftverk', 'SMR', 'thorium'],
        'forvekslinger' => ['atomvåpen', 'kjernevåpen', 'masseødeleggelsesvåpen'],
    ],
];
