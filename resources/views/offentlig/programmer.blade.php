<x-layouts.offentlig tittel="Partiprogrammene" beskrivelse="Arbeidsprogrammene for 2025–2029 og prinsipprogrammene til partiene på Stortinget, digitalisert med lenke til hver setning.">
    <div class="ramme">
        <h1 class="sidetittel">Partiprogrammene</h1>
        <p class="seksjon__ingress">Standpunktene bygger på disse programmene. Hvert program er gjort om til tekst med fast lenke til hvert avsnitt og hver setning, og kontrollert mot originalen.</p>
        <ul class="partiliste partiliste--stor">
            @foreach ($partier as $parti)
                <li style="--partifarge: {{ $parti->farge ?? '#6B7280' }}">
                    <span class="partiliste__navn">{{ $parti->navn }} <abbr>{{ $parti->forkortelse }}</abbr></span>
                    @forelse ($parti->programmer as $program)
                        <a href="{{ route('program', [$parti, $program->slug]) }}">{{ $program->tittel }}@if (! $program->gjeldende) <small>(eldre)</small>@endif</a>
                    @empty
                        <span class="dempet">Ikke ferdig digitalisert ennå</span>
                    @endforelse
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts.offentlig>
