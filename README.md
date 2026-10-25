# HvaMenerPartiet.no

![HvaMenerPartiet.no](public/logo.svg)

HvaMenerPartiet lar en velger søke på et tema, for eksempel «NATO», og se hva hvert parti mener, med et ordrett utdrag fra partiprogrammet og lenke til de siterte setningene. Se [designdokumentet](docs/designdokument.md) for formål, metode og plan.

All analyse skjer på forhånd i bakgrunnsjobber. Nettsiden gjør aldri LLM-kall, den slår bare opp ferdige, kontrollerte standpunkter.

## Status: fase 1, prototype

Denne versjonen dekker første trinn i planen: programmene leses inn fra `docs/Partiprogram`, digitaliseres og klassifiseres for et lite sett temaer, og resultatet samles i en rapport for manuell sammenligning. Hele flyten kan styres og følges i admin (`/admin`). Den offentlige nettsiden viser temaene og programmene som er godkjent (se under).

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

**Digitalisering** (`app/Digitalisering`). `pdftotext -bbox-layout` gir hver linje med side, posisjon og høyde. Overskrifter gjenkjennes på linjehøyde og nummerering, topptekst, bunntekst og sidetall fjernes, orddeling over linjeskift slås sammen, og avsnitt som fortsetter på neste side settes sammen. Forside, kolofon, innholdsfortegnelse og bakside hoppes over (`Sidefilter`), og henvisninger som «(se side 12)» fjernes fra teksten. Hvilke sider som ble utelatt, står i digitaliseringsmerknaden i admin. Punktlister blir ett avsnitt der hvert punkt er en egen setning. Setningsdelingen kjenner norske forkortelser (f.eks., bl.a., jf., nr., ca.).

**Faste ID-er.** Hver setning får en ID som `sv-ap2025-k4-a12-s3` (kapittel 4, avsnitt 12, setning 3). Ved ny import beholder en setning ID-en så lenge teksten er uendret, selv om den har flyttet seg. Standpunkter som pekte på setninger som er borte, sendes tilbake til godkjenning. Lenkeformatet på nettsiden blir `/program/sv/arbeidsprogram-2025/kap-4#a12-s3-s5`.

**Arbeidsprogram og prinsipprogram** klassifiseres hver for seg. Et kombinert dokument deles i prinsipp- og handlingsdel ut fra overskriftene, og delene klassifiseres som om de var to programmer. Visningsregelen i `app/Analyse/Visningsregel.php` er fast kode, ikke en LLM-vurdering.

**Klassifisering** (`app/Analyse`). Korte avsnitt under samme overskrift slås sammen til biter på 200–600 ord. For hvert tema finnes de beste bitene per program med embeddings og søk på navn og synonymer. Uten treff over terskelen settes «Ikke omtalt» uten LLM-kall. Ellers får modellen avsnittene med setnings-ID-er, uten partinavn, og svarer med standpunkt, sikkerhet, begrunnelse og ID-ene til setningene som støtter det. Koden forkaster ID-er som ikke finnes, og utdraget bygges fra setningene i databasen, så sitatet alltid er ordrett. Klassifiseringen kjøres to ganger, og uenighet gir «Delt / uklart» med lav sikkerhet.

## Den offentlige nettsiden

Siden viser bare det som er godkjent i admin: temaer med status «Publisert», standpunkter som er godkjent eller korrigert, og programmer der strukturen er godkjent. Søket gjør aldri LLM-kall.

| Adresse | Innhold |
| --- | --- |
| `/` | Forside med søk, temaer, skalaen og partiene |
| `/sok?q=…` | Søk på tema. Treffer søket navn, slug eller synonym, sendes brukeren rett til temaet. Ellers vises treff, eller nærmeste temaer (skrivefeil) og en knapp for å foreslå temaet |
| `/tema` og `/tema/{slug}` | Alle temaer, og ett tema med påstanden og ett partikort per parti, sortert etter standpunkt. `?visning=arbeidsprogram` eller `prinsipprogram` viser bare den ene programtypen |
| `/program`, `/program/{parti}/{program}` og `…/{kap}` | De digitaliserte programmene. Avsnitt har ID `a12` og setninger `a12-s3`, og `#a12-s3-s5` markerer setning 3–5 |
| `/om` | Om og metode: skala, visningsregel, kjente svakheter |

**Partikortet** har merkelapp, ordrett utdrag (maks tre setninger), lenke til setningene i programmet (eller PDF-en som reserve), «Prinsipielt syn», sikkerhet og «Vis mer» med prinsipprogrammets utdrag og «Meld fra om feil». Visningen bygges i `app/Visning/Temaoversikt.php` med den faste visningsregelen. Partier uten godkjent standpunkt står under «Ikke ferdig kontrollert».

**Søket** (`app/Sok/Temasok.php`) går i PHP over de publiserte temaene, så det virker likt i MySQL og SQLite. Autofullføringen er en `<datalist>` med navn og synonymer.

**Fra besøkende.** «Foreslå tema» lagres i `temaforslag` (like forslag telles opp) og «Meld fra om feil» i `tilbakemeldinger`. Begge vises i admin under «Fra besøkende». Skjemaene har et skjult felt mot roboter og er begrenset til 10 innsendinger i minuttet.

**Profil.** Logoen er en snakkeboble med tre søyler av ulik lengde, i blekkblått og grått med fiolett aksent, bevisst uten partifarger. Filer: `public/logo.svg`, `public/favicon.svg` (følger lyst/mørkt tema), `favicon.ico`, `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`, `site.webmanifest` og `delingsbilde.png`. Merkelappene bruker blått for «for» og oransje for «mot» i stedet for grønt og rødt, så de ikke leses som riktig og galt, og partifargene vises bare som en stripe på kortet. Stilen står i `resources/css/app.css` og krever `npm run build`.

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
| Tema | Velg visning | Samlet / Bare arbeidsprogram / Bare prinsipprogram |
| Tilbakemelding | Meld fra om feil | «Tema – Parti» |

## Søkemotorer, LLM-er og cache

Den offentlige siden har det søkemotorer og LLM-er ser etter. Alt lages ut fra rutene og databasen, så nye sider kommer med av seg selv.

| Adresse | Innhold |
| --- | --- |
| `/robots.txt` | Slipper inn alle, men sperrer `/admin`, `/horizon` og tekniske stier. Utenfor produksjon: `Disallow: /` |
| `/sitemap.xml` | Alle offentlige sider |
| `/llms.txt` | Kort om tjenesten og metoden, med lenker til alle offentlige sider ([llmstxt.org](https://llmstxt.org)) |
| `/llms-full.txt` | Alle publiserte temaer med hvert partis godkjente standpunkt, ordrett utdrag og kilde, i én Markdown-fil |
| `/.well-known/security.txt` | Kontakt for sikkerhetshull: [Security Advisories](https://github.com/Laaknor/HvaMenerPartiet/security/advisories/new) i dette repoet ([RFC 9116](https://www.rfc-editor.org/rfc/rfc9116)) |

Indeksering er bare på når `APP_ENV=production` (overstyr med `SEO_INDEKSER`). Ellers får alle svar `X-Robots-Tag: noindex`. Admin og Horizon får det alltid. Navn, beskrivelse, delingsbilde og stier står i `config/seo.php`.

**Metadata.** Legg `<x-seo />` i `<head>` på alle offentlige sider. Den gir tittel, beskrivelse, kanonisk URL, robots, Open Graph, Twitter-kort og Schema.org (JSON-LD). Uten egne verdier brukes nettstedets navn, beskrivelse og `public/delingsbilde.png`.

```blade
<x-seo tittel="NATO" beskrivelse="Hva mener partiene om NATO? Se standpunkt og ordrette utdrag fra alle partiprogrammene." />
```

**Sitemap og llms.txt.** GET-ruter i `routes/web.php` uten parametere kommer med automatisk. Gi dem gjerne tittel og beskrivelse for llms.txt. En rute med parametere trenger en kilde som lister sidene:

```php
Route::get('om', OmController::class)->sitemap(tittel: 'Om tjenesten', beskrivelse: 'Metode, skala og kjente svakheter');
Route::get('tema/{tema:slug}', TemaController::class)->sitemap(TemaSider::class);
Route::get('sok', SokController::class)->utenforSitemap();
```

```php
class TemaSider implements \App\Seo\Sitemapkilde
{
    public function sider(): iterable
    {
        foreach (Tema::where('status', Temastatus::Publisert)->get() as $tema) {
            yield new Side(url: route('tema', $tema), tittel: $tema->navn, beskrivelse: $tema->pastand, sistEndret: $tema->updated_at);
        }
    }
}
```

**Cache.** Offentlige sider lagres ferdig rendret i Redis i 24 timer (`SIDECACHE_LEVETID`). Det gjelder alle GET-sider i web-gruppen, unntatt admin, Horizon, innloggede brukere, feilsider og svar med flash-meldinger. CSRF-tokenet settes inn per besøkende, så skjemaer og Livewire virker. Svarene har `ETag`, så nettleseren får `304` når siden er uendret, og `X-Sidecache: treff` eller `lagret` viser hva som skjedde.

Cachen tømmes

- når innhold endres: alle lagringer og slettinger av partier, programmer, kapitler, avsnitt, setninger, temaer og standpunkter, for eksempel en godkjenning i admin (trait `TommerSidecache`),
- ved ny deployment: `php artisan optimize` tømmer den, og nøkkelen inneholder deployet versjon (git-commit, app-mappe og byggede ressurser, eller `APP_DEPLOYMENT` hvis den er satt),
- manuelt med `php artisan sidecache:tom`.

Cachen er av når `APP_ENV=local`, og i testene. En rute holdes utenfor med `->withoutMiddleware(\App\Http\Middleware\Sidecache::class)`. Byggede ressurser i `public/build/assets` har hash i navnet og caches i ett år av nettleseren (se `public/.htaccess`; bruker du nginx, legg inn tilsvarende `Cache-Control: public, max-age=31536000, immutable`).

## Tester

```sh
php artisan test
```

Testene bruker SQLite i minnet og en falsk Ollama. Digitaliseringstestene bruker `tests/Fixtures/testprogram.pdf`, et lite fiktivt program laget med `tests/Fixtures/lag_testprogram.py`, og krever `pdftotext`. CI kjører testene mot både SQLite og MySQL.

## Lisens

MIT. Se [LICENSE](LICENSE).
