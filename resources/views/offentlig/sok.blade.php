<x-layouts.offentlig
    :tittel="'Søk: '.$sokeord"
    :indekser="false"
    :sok="$sokeord"
    sokekategori="Tema"
    :treff="$treff->count()"
    :forslag="$forslag"
    :sokeverdi="$sokeord"
>
    <div class="ramme">
        <h1 class="sidetittel">Søk etter «{{ $sokeord }}»</h1>

        @if ($treff->isNotEmpty())
            <p>{{ $treff->count() }} {{ $treff->count() === 1 ? 'tema' : 'temaer' }} passer med søket.</p>
            <ul class="temaliste">
                @foreach ($treff as $tema)
                    <li><a href="{{ route('tema', [$tema, 'sok' => $sokeord]) }}"><strong>{{ $tema->navn }}</strong><span>{{ $tema->pastand }}</span></a></li>
                @endforeach
            </ul>
        @else
            <div class="ingen-treff">
                <h2>Vi har ikke analysert «{{ $sokeord }}» ennå</h2>
                <p>Søket viser bare temaer der partienes standpunkt allerede er kontrollert. Svarene lages ikke mens du venter, så de kan sjekkes før de vises.</p>

                @if ($naermeste->isNotEmpty())
                    <h3>Mente du</h3>
                    <ul class="brikker">
                        @foreach ($naermeste as $tema)
                            <li><a class="brikke" href="{{ route('tema', [$tema, 'sok' => $sokeord]) }}" data-spor-kategori="Søk" data-spor-handling="Velg forslag" data-spor-navn="{{ $tema->navn }}">{{ $tema->navn }}</a></li>
                        @endforeach
                    </ul>
                @endif

                <form class="foresla" method="post" action="{{ route('tema.foresla') }}">
                    @csrf
                    <input type="hidden" name="tema" value="{{ $sokeord }}">
                    <label class="skjult-felt" aria-hidden="true">Nettside <input type="text" name="nettside" tabindex="-1" autocomplete="off"></label>
                    <p>Savner du temaet? Foreslå det, så kan det bli analysert i neste runde.</p>
                    <button type="submit" class="knapp knapp--primar" data-spor-kategori="Tema" data-spor-handling="Foreslå tema" data-spor-navn="{{ $sokeord }}">Foreslå «{{ $sokeord }}» som tema</button>
                </form>
            </div>
        @endif

        <p><a class="lenke-pil" href="{{ route('temaer') }}">Se alle temaene</a></p>
    </div>
</x-layouts.offentlig>
