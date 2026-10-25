<?php

return [

    /*
    | Ollama brukes til all tolkning og alle embeddings. Nettsiden gjør aldri
    | kall hit; bare bakgrunnsjobbene gjør det.
    */
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'modell' => env('OLLAMA_MODELL', 'gemma3:27b'),
        'embeddingmodell' => env('OLLAMA_EMBEDDINGMODELL', 'bge-m3'),
        'tidsavbrudd' => (int) env('OLLAMA_TIDSAVBRUDD', 600),
        'samtidige' => (int) env('OLLAMA_SAMTIDIGE', 2),
        'kontekstlengde' => (int) env('OLLAMA_KONTEKSTLENGDE', 16384),
    ],

    'digitalisering' => [
        // Programfilene lagres på denne disken, under mappen «programmer».
        'disk' => env('PROGRAM_DISK', 'local'),
        'pdftotext' => env('PDFTOTEXT_STI', 'pdftotext'),
    ],

    'analyse' => [
        // Tekstbiter for gjenfinning: korte avsnitt slås sammen til minst så mange ord.
        'bit_min_ord' => 200,
        'bit_maks_ord' => 600,
        // Hvor mange tekstbiter per program modellen får se.
        'antall_biter' => (int) env('ANALYSE_ANTALL_BITER', 8),
        // Laveste likhet (cosinus) for at en bit regnes som treff uten nøkkelordtreff.
        'terskel' => (float) env('ANALYSE_TERSKEL', 0.5),
        // Antall kjøringer per standpunkt. Uenighet gir «Delt / uklart».
        'kjoringer' => (int) env('ANALYSE_KJORINGER', 2),
        // Maks antall setninger i utdraget.
        'utdrag_setninger' => 3,
    ],
];
