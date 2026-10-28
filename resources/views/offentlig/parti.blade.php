@php
    $jsonld = [[
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Partiene', 'item' => route('partier')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $parti->navn, 'item' => route('parti', $parti)],
        ],
    ]];
    $antall = $grupper->flatten(1)->count();
@endphp
<x-layouts.offentlig :tittel="$parti->navn" :beskrivelse="'Hva mener '.$parti->navn.'? Standpunktene i alle temaene, med ordrett sitat fra partiprogrammet.'" :jsonld="$jsonld" :kanonisk="route('parti', $parti)" :forslag="$forslag">
    <div class="ramme" style="--partifarge: {{ $parti->farge ?? '#6B7280' }}">
        <nav class="sti" aria-label="Brødsmuler">
            <a href="{{ route('partier') }}">Partiene</a> <span aria-hidden="true">/</span> <span>{{ $parti->navn }}</span>
        </nav>

        <header class="partihode">
            <h1>Hva mener {{ $parti->navn }}?</h1>
            @if ($parti->programmer->isNotEmpty())
                <p class="partihode__programmer">Bygger på
                    @foreach ($parti->programmer as $program)
                        <a href="{{ route('program', [$parti, $program->slug]) }}">{{ $program->tittel }}</a>@if (! $loop->last) og @endif
                    @endforeach
                </p>
            @endif
        </header>

        @if ($antall === 0)
            <p class="tom">Ingen standpunkter er ferdig kontrollert for partiet ennå.</p>
        @else
            <section class="svar" aria-label="Kort svar">
                <ul class="svar__grupper">
                    @foreach ($grupper as $verdi => $rader)
                        <li class="svar__gruppe">
                            <h2 class="svar__tittel"><span class="merkelapp merkelapp--{{ str_replace('_', '-', $verdi) }}">{{ $rader->first()['kort']->etikett() }}</span> <span class="svar__antall">{{ $rader->count() }} {{ $rader->count() === 1 ? 'tema' : 'temaer' }}</span></h2>
                        </li>
                    @endforeach
                </ul>
                <p class="svar__hjelp"><a href="{{ route('om') }}#skalaen">Hva betyr merkelappene?</a></p>
            </section>

            @foreach ($grupper as $verdi => $rader)
                <h2 class="gruppetittel">{{ $rader->first()['kort']->etikett() }}</h2>
                <ul class="temarader">
                    @foreach ($rader as $rad)
                        @php($kort = $rad['kort'])
                        <li class="temarad">
                            <a class="temarad__lenke" href="{{ route('tema', $rad['tema']) }}#parti-{{ $parti->id }}">
                                <span class="temarad__tema">{{ $rad['tema']->navn }}</span>
                                <span class="temarad__pastand">«{{ $rad['tema']->pastand }}»</span>
                                @if ($kort->kilde && $kort->kilde->utdrag !== '')
                                    <span class="temarad__sitat">«{{ $kort->kilde->kort() }}»</span>
                                @endif
                                @if ($kort->avvikverdi)
                                    <span class="merkelapp merkelapp--liten {{ $kort->merkelappklasse($kort->avvikverdi) }}">Prinsipielt: {{ $kort->avvikverdi->etikett() }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif

        @if ($ikkeKontrollert > 0)
            <p class="ikke-vurdert">{{ $ikkeKontrollert }} {{ $ikkeKontrollert === 1 ? 'tema er' : 'temaer er' }} ikke ferdig kontrollert for partiet ennå.</p>
        @endif

        <p><a class="lenke-pil" href="{{ route('temaer') }}">Sammenlign med andre partier</a></p>
    </div>
</x-layouts.offentlig>
