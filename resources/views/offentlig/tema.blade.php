@php
    use App\Visning\Temaoversikt;
    $beskrivelse = "Hva mener partiene om {$tema->navn}? Se standpunkt og ordrette utdrag fra partiprogrammene, målt mot påstanden «".rtrim($tema->pastand, ".")."».";
    $jsonld = [[
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Temaer', 'item' => route('temaer')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $tema->navn, 'item' => route('tema', $tema)],
        ],
    ]];
    $visninger = [
        Temaoversikt::BEGGE => 'Samlet',
        Temaoversikt::ARBEIDSPROGRAM => 'Bare arbeidsprogram',
        Temaoversikt::PRINSIPPROGRAM => 'Bare prinsipprogram',
    ];
@endphp
<x-layouts.offentlig
    :tittel="$tema->navn"
    :beskrivelse="$beskrivelse"
    :jsonld="$jsonld"
    :kanonisk="route('tema', $tema)"
    :indekser="$visning === Temaoversikt::BEGGE && ! $sokeord"
    :sok="$sokeord"
    sokekategori="Tema"
    :treff="$sokeord ? 1 : null"
    :forslag="$forslag"
    :sokeverdi="$sokeord ?? ''"
>
    <div class="ramme">
        <nav class="sti" aria-label="Brødsmuler">
            <a href="{{ route('temaer') }}">Temaer</a> <span aria-hidden="true">/</span> <span>{{ $tema->navn }}</span>
        </nav>

        <header class="temahode">
            <h1>Hva mener partiene om {{ $tema->navn }}?</h1>
            @if ($tema->beskrivelse)
                <p class="temahode__beskrivelse">{{ $tema->beskrivelse }}</p>
            @endif
            <div class="pastand">
                <span class="pastand__etikett">Standpunktene er målt mot påstanden</span>
                <p class="pastand__tekst">«{{ $tema->pastand }}»</p>
            </div>
        </header>

        @if ($kort->isNotEmpty())
            <div class="fordeling" aria-label="Fordeling">
                @foreach ($fordeling as $verdi => $antall)
                    @php($v = \App\Enums\Standpunktverdi::from($verdi))
                    <span class="fordeling__del">
                        <span class="merkelapp merkelapp--{{ str_replace('_', '-', $verdi) }}">{{ $v->etikett() }}</span>
                        {{ $antall }} {{ $antall === 1 ? 'parti' : 'partier' }}
                    </span>
                @endforeach
            </div>
        @endif

        <nav class="faner" aria-label="Velg program">
            @foreach ($visninger as $nokkel => $navn)
                <a href="{{ $nokkel === Temaoversikt::BEGGE ? route('tema', $tema) : route('tema', [$tema, 'visning' => $nokkel]) }}"
                   @if ($visning === $nokkel) aria-current="page" @endif
                   data-spor-kategori="Tema" data-spor-handling="Velg visning" data-spor-navn="{{ $navn }}">{{ $navn }}</a>
            @endforeach
        </nav>
        <p class="faner__forklaring">
            @switch($visning)
                @case(Temaoversikt::ARBEIDSPROGRAM) Bare arbeidsprogrammene for 2025–2029: hva partiene vil gjennomføre i perioden. @break
                @case(Temaoversikt::PRINSIPPROGRAM) Bare prinsipprogrammene: hva partiene mener på lang sikt. @break
                @default Merkelappen kommer fra arbeidsprogrammet. Prinsipprogrammet vises som «Prinsipielt syn», og brukes bare når arbeidsprogrammet ikke omtaler temaet.
            @endswitch
        </p>

        @if ($kort->isEmpty())
            <p class="tom">Ingen standpunkt er ferdig kontrollert for denne visningen ennå.</p>
        @else
            <div class="kortliste">
                @foreach ($kort as $partikort)
                    <x-partikort :kort="$partikort" :tema="$tema" />
                @endforeach
            </div>
        @endif

        @if ($ikkeVurdert->isNotEmpty())
            <p class="ikke-vurdert">
                <strong>Ikke ferdig kontrollert:</strong>
                {{ $ikkeVurdert->pluck('navn')->join(', ', ' og ') }}.
                Standpunktene vises når de er kontrollert.
            </p>
        @endif

        @if ($andre->isNotEmpty())
            <section class="seksjon">
                <h2 class="seksjon__tittel">Andre temaer</h2>
                <ul class="brikker">
                    @foreach ($andre as $t)
                        <li><a class="brikke" href="{{ route('tema', $t) }}">{{ $t->navn }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.offentlig>
