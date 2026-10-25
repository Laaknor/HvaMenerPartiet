# HvaMenerPartiet

HvaMenerPartiet lar en velger søke på et tema, for eksempel «NATO», og se hva hvert parti mener, med et ordrett utdrag fra partiprogrammet og lenke til de siterte setningene. Se [designdokumentet](docs/designdokument.md) for formål, metode og plan.

All analyse skjer på forhånd i bakgrunnsjobber. Nettsiden gjør aldri LLM-kall, den slår bare opp ferdige, kontrollerte standpunkter.

## Status: fase 1, prototype

Denne versjonen dekker første trinn i planen: programmene leses inn fra `docs/Partiprogram`, digitaliseres og klassifiseres for et lite sett temaer, og resultatet samles i en rapport for manuell sammenligning. Hele flyten kan styres og følges i admin (`/admin`). Den offentlige nettsiden kommer i et senere trinn.

## Teknologi

| Del | Valg |
| --- | --- |
| Rammeverk | Laravel 13 (PHP 8.3) |
| Database | MySQL. Embeddings lagres som binære float32-vektorer (BLOB) |
| Kø og cache | Redis med Laravel Horizon |
| PDF til tekst | poppler `pdftotext -bbox-layout` |
| LLM og embeddings | Ollama, med JSON-skjema for strukturerte svar |
| Admin | FilamentPHP: prosessflyt, programmer, temaer, standpunkter og kjøringer |

## Kom i gang

Krever PHP 8.3, Composer, `poppler-utils` og Docker (eller egne installasjoner av MySQL, Redis og Ollama).

```sh
composer install
cp .env.example .env
php artisan key:generate

docker compose up -d                       # MySQL, Redis og Ollama
docker compose exec ollama ollama pull bge-m3
docker compose exec ollama ollama pull gemma3:27b

php artisan migrate --seed                 # partier, programmer og temaer
php artisan horizon                        # i et eget vindu
php artisan schedule:work                  # i et eget vindu (i drift: cron med schedule:run hvert minutt)
```

Ollama bør helst kjøre på en egen server med GPU. Sett `OLLAMA_URL` og `OLLAMA_SAMTIDIGE` (antall samtidige jobber serveren tåler) i `.env`. Modellen for klassifisering velges i `OLLAMA_MODELL` og skal velges ved måling mot testsettet i fase 2.

Embeddings kan gå til en annen server enn tolkningen, med `OLLAMA_EMBEDDING_URL`. Er serveren llama.cpp-server og ikke Ollama, må den startes med `--embeddings` og en embeddingmodell (for eksempel bge-m3 i GGUF), og `OLLAMA_EMBEDDING_API=openai` må settes så `/v1/embeddings` brukes. En server som ikke støtter embeddings, gir en forklarende feil uten nye forsøk.

### Tilgang til admin og Horizon

Utenfor lokalt miljø (`APP_ENV` er ikke `local`) slipper `/admin` og `/horizon` bare inn brukere med admintilgang. Andre får 403. Gi tilgang slik, og kommandoen lager brukeren hvis den ikke finnes:

```sh
php artisan migrate
php artisan bruker:admin deg@eksempel.no
php artisan bruker:admin deg@eksempel.no --fjern   # fjerner tilgangen
```

## Partiprogrammene ligger i docs/

Alle partiprogram-PDF-ene ligger i `docs/Partiprogram/` i repoet, og det er der systemet leser dem. Hvert program peker på sin fil med `kildefil` i `database/data/programmer.php` (stien er relativ til `docs/Partiprogram`). Et nytt eller endret program legges inn ved å committe PDF-en dit og enten oppdatere `programmer.php` og kjøre `php artisan db:seed --class=ProgramSeeder`, eller velge filen på programmet i admin.

Ved innlesing kopieres filen til lagringsdisken med SHA-256 i navnet, så hver versjon som er digitalisert beholdes. Endres filen i `docs/`, leses den inn og digitaliseres på nytt av seg selv, og strukturen må gjennomgås igjen.

Disse mangler fortsatt PDF i `docs/`: prinsipprogrammene til Høyre, Rødt, MDG, KrF og Venstre.

## Prosessflyten i admin

Forsiden i admin (`/admin`) viser hvor programmene står, hva som kjører nå og hva som har feilet. Stegene er:

1. **Innlesing** fra `docs/Partiprogram` (kø `import`)
2. **Digitalisering og oppsplitting** i kapitler, avsnitt og setninger, og i prinsipp- og handlingsdel for kombinerte dokumenter (kø `import`)
3. **Gjennomgang** av strukturen. Dette er manuelt: åpne programmet, sjekk kapitler og avsnitt mot PDF-en, merk prinsipp- eller handlingsdel om det trengs, og trykk «Godkjenn strukturen»
4. **Embeddings** (kø `embeddings`)
5. **Klassifisering** for hvert godkjent tema mot hvert godkjent program (kø `klassifisering`)
6. **Gjennomgang av standpunkter** under «Standpunkter», med uenighet og lav sikkerhet først

**Automatisk drift.** Planleggeren (`flyt:planlegg`) starter neste steg av seg selv når det er ledig kapasitet. Den kjøres hvert minutt av Laravel-planleggeren og hver gang en jobb blir ferdig. Kapasiteten er `FLYT_KAPASITET_IMPORT` jobber for innlesing og digitalisering og `OLLAMA_SAMTIDIGE` for embeddings og klassifisering, de samme tallene som Horizon bruker. Automatisk drift slås av og på med knappen på forsiden eller `php artisan flyt:planlegg --av` / `--pa`.

Hver jobb får en rad under «Kjøringer», med status, varighet og feilmelding. En kjøring som har feilet, startes ikke automatisk igjen før noe er endret (ny fil, ny innlesing eller ny godkjenning). Den kan startes på nytt med «Prøv igjen». Alle steg kan også startes for hånd fra programsiden.

## Arbeidsflyt med kommandoer

```sh
php artisan program:hent --alle --synkront           # les inn PDF-ene fra docs/Partiprogram
# --fra-nett laster ned fra partienes nettsider i stedet

php artisan program:digitaliser --alle --synkront    # kapitler, avsnitt, setninger
php artisan program:status                           # oversikt
php artisan program:vis sv-ap2025                    # kontroller strukturen
php artisan program:vis sv-ap2025 --kapittel=kap-4 --setninger

# Kombinerte dokumenter (Sp, FrP): merk kapitler som overskriftene ikke avgjorde
php artisan program:merk-del sp-kp2025 prinsipp kap-1 kap-2

php artisan program:godkjenn sv-ap2025 sv-pp2023     # starter embeddings i køen
php artisan tema:klassifiser                         # én jobb per tema × program × del
php artisan prototype:rapport                        # Markdown i storage/app/private/rapporter
```

Uten `--synkront` legges arbeidet i køen og kjøres av Horizon, og jobbene vises under «Kjøringer» i admin. Køene er `import`, `embeddings` og `klassifisering`. Kommandoene trengs ikke når automatisk drift er på, bortsett fra `program:godkjenn` om du ikke godkjenner i admin.

Rapporten viser standpunkt per parti slik partikortet ville vist det, med utdrag, kildelenke, modellens begrunnelse og en tom kolonne for manuell vurdering. Kriteriet for å gå videre til fase 2 er at resultatene stemmer med den manuelle vurderingen.

## Hvordan det virker

**Programregister.** Partiene står i `database/data/partier.php`, programmene i `database/data/programmer.php` og prototypetemaene i `database/data/temaer.php`. Hvert program har en type: arbeidsprogram, prinsipprogram eller kombinert.

**Digitalisering** (`app/Digitalisering`). `pdftotext -bbox-layout` gir hver linje med side, posisjon og høyde. Overskrifter gjenkjennes på linjehøyde og nummerering, topptekst, bunntekst og sidetall fjernes, orddeling over linjeskift slås sammen, og avsnitt som fortsetter på neste side settes sammen. Punktlister blir ett avsnitt der hvert punkt er en egen setning. Setningsdelingen kjenner norske forkortelser (f.eks., bl.a., jf., nr., ca.).

**Faste ID-er.** Hver setning får en ID som `sv-ap2025-k4-a12-s3` (kapittel 4, avsnitt 12, setning 3). Ved ny import beholder en setning ID-en så lenge teksten er uendret, selv om den har flyttet seg. Standpunkter som pekte på setninger som er borte, sendes tilbake til godkjenning. Lenkeformatet på nettsiden blir `/program/sv/arbeidsprogram-2025/kap-4#a12-s3-s5`.

**Arbeidsprogram og prinsipprogram** klassifiseres hver for seg. Et kombinert dokument deles i prinsipp- og handlingsdel ut fra overskriftene, og delene klassifiseres som om de var to programmer. Visningsregelen i `app/Analyse/Visningsregel.php` er fast kode, ikke en LLM-vurdering.

**Klassifisering** (`app/Analyse`). Korte avsnitt under samme overskrift slås sammen til biter på 200–600 ord. For hvert tema finnes de beste bitene per program med embeddings og søk på navn og synonymer. Uten treff over terskelen settes «Ikke omtalt» uten LLM-kall. Ellers får modellen avsnittene med setnings-ID-er, uten partinavn, og svarer med standpunkt, sikkerhet, begrunnelse og ID-ene til setningene som støtter det. Koden forkaster ID-er som ikke finnes, og utdraget bygges fra setningene i databasen, så sitatet alltid er ordrett. Klassifiseringen kjøres to ganger, og uenighet gir «Delt / uklart» med lav sikkerhet.

## Analyse med Matomo

Den offentlige siden sporer bruk med Matomo. Sett `MATOMO_HOST` (for eksempel `analytics.laaknor.no`) og `MATOMO_SITE_ID` i `.env`. Står en av dem tomt, legges ingen sporingskode inn. Admin (`/admin`) spores aldri.

Sporingskoden ligger i komponenten `<x-matomo />` (`resources/views/components/matomo.blade.php`) og legges i `<head>` på alle offentlige sider. Den sporer sidevisninger og klikk på eksterne lenker og nedlastinger (for eksempel PDF-en av et program).

**Interne søk.** En søkeresultatside skal spore søket i stedet for en vanlig sidevisning:

```blade
<x-matomo :sok="$sokeord" sokekategori="Tema" :treff="$antallTreff" />
```

Antall treff 0 viser søk uten resultat i Matomo-rapporten, som er nyttig for å finne temaer som mangler. Søk som oppdateres uten ny sidelasting (Livewire) spores med `HvaMenerPartiet.sporSok(sokeord, kategori, treff)` i JavaScript.

**Hendelser.** Merk elementer i HTML-en med `data-spor-*`, så sendes en Matomo-hendelse ved klikk. Et `<details>`-element sendes når det åpnes.

```blade
<details data-spor-kategori="Standpunkt" data-spor-handling="Åpne" data-spor-navn="NATO – Høyre">…</details>
<a href="…" data-spor-kategori="Kilde" data-spor-handling="Programlenke" data-spor-navn="Høyre arbeidsprogram 2025 s. 34">Les i programmet</a>
```

`data-spor-verdi` kan legges til for en tallverdi. Fra JavaScript: `HvaMenerPartiet.spor(kategori, handling, navn, verdi)`. Faste navn gjør rapportene lettere å lese:

| Kategori | Handling | Navn |
|---|---|---|
| Standpunkt | Åpne | «Tema – Parti» |
| Kilde | Programlenke / Stortingsvotering | «Parti program år s. side» |
| Søk | Velg forslag | Temaet i autofullføringen |
| Tema | Foreslå tema | Søkeordet |
| Tilbakemelding | Meld fra om feil | «Tema – Parti» |

## Tester

```sh
php artisan test
```

Testene bruker SQLite i minnet og en falsk Ollama. Digitaliseringstestene bruker `tests/Fixtures/testprogram.pdf`, et lite fiktivt program laget med `tests/Fixtures/lag_testprogram.py`, og krever `pdftotext`. CI kjører testene mot både SQLite og MySQL.

## Lisens

MIT. Se [LICENSE](LICENSE).
