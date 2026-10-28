<x-layouts.offentlig tittel="Partiene" beskrivelse="Partiene på Stortinget. Se alt hvert parti mener, tema for tema, og programmene standpunktene bygger på." :forslag="$forslag">
    <div class="ramme">
        <h1 class="sidetittel">Partiene</h1>
        <p class="seksjon__ingress">De ni partiene på Stortinget etter valget i 2025. Velg et parti for å se hva det mener i alle temaene.</p>
        <ul class="partiliste">
            @foreach ($partier as $parti)
                <li style="--partifarge: {{ $parti->farge ?? '#6B7280' }}">
                    <a class="partiliste__navn" href="{{ route('parti', $parti) }}">{{ $parti->navn }} <abbr>{{ $parti->forkortelse }}</abbr></a>
                    @foreach ($parti->programmer as $program)
                        <a class="partiliste__program" href="{{ route('program', [$parti, $program->slug]) }}">{{ $program->tittel }}</a>
                    @endforeach
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts.offentlig>
