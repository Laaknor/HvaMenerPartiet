<x-layouts.offentlig :forslag="$forslag" :toppsok="false">
    <section class="helt">
        <div class="ramme helt__innhold">
            <h1 class="helt__tittel">Hva mener partiene?</h1>
            <p class="helt__ingress">Søk på et tema og se hva hvert parti på Stortinget mener, med ordrett sitat fra partiprogrammet og lenke rett til setningen.</p>
            <x-temasok stor :forslag="$forslag" id="forsidesok" />

            @if ($temaer->isNotEmpty())
                <div class="helt__populare">
                    <span class="helt__populare-etikett">Prøv for eksempel:</span>
                    <ul class="brikker">
                        @foreach ($temaer->take(10) as $tema)
                            <li><a class="brikke" href="{{ route('tema', $tema) }}">{{ $tema->navn }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

    <section class="ramme seksjon">
        <h2 class="seksjon__tittel">Slik leser du svaret</h2>
        <ol class="steg">
            <li>
                <h3>Én påstand per tema</h3>
                <p>Hvert tema har en nøytral påstand, for eksempel «Norge bør være medlem av NATO». Standpunktet sier om partiet er for eller mot den.</p>
            </li>
            <li>
                <h3>Ordrett fra programmet</h3>
                <p>Utdraget er alltid sitert ordrett, og lenken tar deg rett til setningene i partiprogrammet.</p>
            </li>
            <li>
                <h3>Kontrollert før publisering</h3>
                <p>Standpunktene lages på forhånd og kontrolleres av et menneske før de vises. Ser du en feil, kan du melde fra.</p>
            </li>
        </ol>
        <x-skala />
    </section>

    <section class="ramme seksjon">
        <h2 class="seksjon__tittel">Temaer</h2>
        @if ($temaer->isEmpty())
            <p class="tom">Ingen temaer er publisert ennå. De første kommer når standpunktene er kontrollert.</p>
        @else
            <ul class="temaliste">
                @foreach ($temaer->take(24) as $tema)
                    <li><a href="{{ route('tema', $tema) }}"><strong>{{ $tema->navn }}</strong><span>{{ $tema->pastand }}</span></a></li>
                @endforeach
            </ul>
            @if ($temaer->count() > 24)
                <p><a class="lenke-pil" href="{{ route('temaer') }}">Se alle {{ $temaer->count() }} temaene</a></p>
            @endif
        @endif
    </section>

    <section class="ramme seksjon">
        <h2 class="seksjon__tittel">Partiene</h2>
        <p class="seksjon__ingress">De ni partiene på Stortinget etter valget i 2025. Arbeidsprogrammet for 2025–2029 og prinsipprogrammet vises hver for seg.</p>
        <ul class="partiliste">
            @foreach ($partier as $parti)
                <li style="--partifarge: {{ $parti->farge ?? '#6B7280' }}">
                    <span class="partiliste__navn">{{ $parti->navn }} <abbr>{{ $parti->forkortelse }}</abbr></span>
                    @foreach ($parti->programmer as $program)
                        <a href="{{ route('program', [$parti, $program->slug]) }}">{{ $program->tittel }}</a>
                    @endforeach
                </li>
            @endforeach
        </ul>
    </section>
</x-layouts.offentlig>
