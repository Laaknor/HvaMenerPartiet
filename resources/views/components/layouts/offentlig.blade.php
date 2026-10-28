{{--
    Felles ramme for de offentlige sidene: metadata, Matomo, topp og bunn.

    <x-layouts.offentlig tittel="NATO" beskrivelse="…" :forslag="$forslag">…</x-layouts.offentlig>

    sok, sokekategori og treff sendes videre til <x-matomo /> på søkesider.
--}}
@props([
    'tittel' => null,
    'beskrivelse' => null,
    'jsonld' => [],
    'kanonisk' => null,
    'indekser' => true,
    'sok' => null,
    'sokekategori' => null,
    'treff' => null,
    'forslag' => [],
    'toppsok' => true,
    'sokeverdi' => '',
])
<!DOCTYPE html>
<html lang="nb">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-seo :tittel="$tittel" :beskrivelse="$beskrivelse" :jsonld="$jsonld" :kanonisk="$kanonisk" :indekser="$indekser" />
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#1F2A44">
    @fonts
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <x-matomo :sok="$sok" :sokekategori="$sokekategori" :treff="$treff" />
</head>
<body class="offentlig">
    <a class="hopp" href="#innhold">Gå til innholdet</a>

    <header class="topp">
        <div class="ramme topp__innhold">
            <a href="{{ route('forside') }}" class="topp__logo" aria-label="HvaMenerPartiet.no, til forsiden"><x-logo /></a>
            <nav class="topp__meny" aria-label="Hovedmeny">
                <a href="{{ route('temaer') }}" @class(['aktiv' => request()->routeIs('temaer', 'tema')])>Temaer</a>
                <a href="{{ route('partier') }}" @class(['aktiv' => request()->routeIs('partier', 'parti')])>Partiene</a>
                <a href="{{ route('programmer') }}" @class(['aktiv' => request()->routeIs('programmer', 'program', 'program.*')])>Programmene</a>
                <a href="{{ route('om') }}" @class(['aktiv' => request()->routeIs('om')])>Om og metode</a>
            </nav>
            @if ($toppsok)
                <x-temasok class="topp__sok" id="toppsok" :forslag="$forslag" :verdi="$sokeverdi" />
            @endif
        </div>
    </header>

    @if (session('status'))
        <div class="ramme"><p class="melding" role="status">{{ session('status') }}</p></div>
    @endif
    @if ($errors->any())
        <div class="ramme"><p class="melding melding--feil" role="alert">{{ $errors->first() }}</p></div>
    @endif

    <main id="innhold" class="innhold">
        {{ $slot }}
    </main>

    <footer class="bunn">
        <div class="ramme bunn__innhold">
            <div>
                <x-logo class="logo--liten" />
                <p>Standpunktene er hentet fra partienes egne programmer, kontrollert manuelt og vist med ordrett sitat og lenke til kilden. Siden anbefaler ikke parti.</p>
                <p class="bunn__kilde">Historiske partiprogrammer: Sikt – Kunnskapssektorens tjenesteleverandør. (u.å.). <cite>Data om det politiske system</cite>. Hentet 25. oktober 2026, fra <a href="https://polsys.sikt.no/">https://polsys.sikt.no/</a>.</p>
            </div>
            <ul class="bunn__lenker">
                <li><a href="{{ route('om') }}">Om og metode</a></li>
                <li><a href="{{ route('partier') }}">Partiene</a></li>
                <li><a href="{{ route('programmer') }}">Partiprogrammene</a></li>
                <li><a href="{{ url('llms-full.txt') }}">Alle standpunkt som tekst</a></li>
                <li><a href="{{ config('seo.kildekode') }}">Åpen kildekode (MIT)</a></li>
            </ul>
        </div>
    </footer>
</body>
</html>
