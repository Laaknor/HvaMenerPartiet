<x-layouts.offentlig tittel="Alle temaer" beskrivelse="Alle temaer der partienes standpunkt er analysert og kontrollert, med ordrette utdrag fra partiprogrammene." :forslag="$forslag">
    <div class="ramme">
        <h1 class="sidetittel">Alle temaer</h1>
        <p class="seksjon__ingress">Hvert tema har en påstand som partienes standpunkt er målt mot.</p>
        @if ($temaer->isEmpty())
            <p class="tom">Ingen temaer er publisert ennå.</p>
        @else
            <ul class="temaliste">
                @foreach ($temaer as $tema)
                    <li><a href="{{ route('tema', $tema) }}"><strong>{{ $tema->navn }}</strong><span>{{ $tema->pastand }}</span></a></li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.offentlig>
