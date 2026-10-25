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
        // Embeddings kan gå til en annen server enn tolkningen. Tom verdi
        // betyr samme server som OLLAMA_URL.
        'embedding_url' => env('OLLAMA_EMBEDDING_URL') ?: env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        // «ollama» bruker /api/embed. «openai» bruker /v1/embeddings, som
        // llama.cpp-server (startet med --embeddings), vLLM og andre støtter.
        'embedding_api' => env('OLLAMA_EMBEDDING_API', 'ollama'),
        'tidsavbrudd' => (int) env('OLLAMA_TIDSAVBRUDD', 600),
        'samtidige' => (int) env('OLLAMA_SAMTIDIGE', 2),
        'kontekstlengde' => (int) env('OLLAMA_KONTEKSTLENGDE', 16384),
        // Tenkemodus (qwen3, deepseek-r1 o.l.) slås av: med JSON-skjema gir den
        // ofte tomt eller ødelagt svar, og den gjør klassifiseringen mye tregere.
        'tenk' => filter_var(env('OLLAMA_TENK', false), FILTER_VALIDATE_BOOL),
    ],

    'digitalisering' => [
        // Kilden for alle partiprogrammer: PDF-ene i docs/Partiprogram i repoet.
        // Programmene peker hit med «kildefil».
        'programmappe' => env('PROGRAMMAPPE', base_path('docs/Partiprogram')),
        // Ved innlesing kopieres filen hit, under mappen «programmer», med
        // hash i navnet, slik at hver versjon som er digitalisert beholdes.
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

    /*
    | Prosessflyten: planleggeren (flyt:planlegg, hvert minutt og hver gang en
    | jobb er ferdig) starter neste steg for programmer og temaer når det er
    | ledig kapasitet. Kapasiteten bør være lik antall prosesser i Horizon.
    */
    'flyt' => [
        'automatisk' => (bool) env('FLYT_AUTOMATISK', true),
        'kapasitet' => [
            'import' => (int) env('FLYT_KAPASITET_IMPORT', 2),
            'ollama' => (int) env('OLLAMA_SAMTIDIGE', 2),
        ],
        // En jobb som har stått som «kjører» lenger enn dette, regnes som avbrutt.
        'maks_kjoretid' => (int) env('FLYT_MAKS_KJORETID', 1200),
        // Historiske programmer behandles bare når ingen gjeldende programmer
        // venter i samme kapasitetsgruppe. false stopper dem helt.
        'historiske' => (bool) env('FLYT_HISTORISKE', true),
    ],
];
