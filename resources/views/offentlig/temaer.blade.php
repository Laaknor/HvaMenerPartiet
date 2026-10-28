<x-layouts.offentlig tittel="Alle temaer" beskrivelse="Alle temaer der partienes standpunkt er analysert og kontrollert, i én tabell der du kan sammenligne partiene." :forslag="$forslag">
    <div class="ramme">
        <h1 class="sidetittel">Alle temaer</h1>
        <p class="seksjon__ingress">Hvert tema har en påstand som partienes standpunkt er målt mot. Trykk på en rute for å se sitatet fra programmet.</p>
        @if ($tabell->rader === [])
            <p class="tom">Ingen temaer er publisert ennå.</p>
        @else
            <x-standpunkttabell :tabell="$tabell" velger />
        @endif
    </div>
</x-layouts.offentlig>
