<x-layouts.offentlig :forslag="$forslag" :toppsok="false">
    <section class="helt">
        <div class="ramme helt__innhold">
            <h1 class="helt__tittel">Hva mener partiene?</h1>
            <p class="helt__ingress">Standpunktene til partiene på Stortinget, tema for tema, med ordrett sitat fra partiprogrammet.</p>
            <x-temasok stor :forslag="$forslag" id="forsidesok" />
        </div>
    </section>

    <section class="ramme seksjon" aria-labelledby="oversikt-tittel">
        <h2 class="seksjon__tittel" id="oversikt-tittel">Oversikt</h2>
        @if ($tabell->rader === [])
            <p class="tom">Ingen temaer er publisert ennå. De første kommer når standpunktene er kontrollert.</p>
        @else
            <p class="seksjon__ingress">
                @if ($harPopulare)
                    De mest besøkte temaene og noen tilfeldig valgte.
                @else
                    Et tilfeldig utvalg av temaene.
                @endif
                Trykk på en rute for å se sitatet.
            </p>
            <x-standpunkttabell :tabell="$tabell" data-tilfeldige="{{ $tilfeldige }}" />
            <p class="oversikt__fot">
                <a class="lenke-pil" href="{{ route('temaer') }}">Se alle {{ $antallTemaer }} temaene og sammenlign partiene</a>
            </p>
        @endif
    </section>
</x-layouts.offentlig>
