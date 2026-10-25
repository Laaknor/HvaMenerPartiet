{{--
    Ett hovedkapittel i et digitalisert program. Avsnitt har id «a12» og
    setninger «a12-s3». Lenken …/kap-4#a12-s3-s5 markerer setning 3 til 5
    (se resources/js/app.js).
--}}
<x-layouts.offentlig :tittel="$kapittel->tittel.' – '.$parti->navn.', '.$program->tittel" :beskrivelse="$parti->navn.', '.$program->tittel.': '.$kapittel->tittel.'.'">
    <div class="ramme programside" style="--partifarge: {{ $parti->farge ?? '#6B7280' }}">
        <nav class="sti" aria-label="Brødsmuler">
            <a href="{{ route('programmer') }}">Programmene</a> <span aria-hidden="true">/</span>
            <a href="{{ route('program', [$parti, $program->slug]) }}">{{ $parti->navn }}, {{ $program->tittel }}</a>
        </nav>

        <article class="programtekst">
            <h1>{{ $kapittel->tittel }}</h1>
            @php($forrigeKapittel = $kapittel->id)
            @foreach ($avsnitt as $a)
                @if ($a->kapittel_id !== $forrigeKapittel)
                    @php($forrigeKapittel = $a->kapittel_id)
                    <h2 id="k{{ $a->kapittel->id }}" @class(['underoverskrift', 'underoverskrift--3' => $a->kapittel->niva > 2])>{{ $a->kapittel->tittel }}</h2>
                @endif
                <div class="avsnitt" id="a{{ $a->nr }}">
                    <a class="avsnitt__nr" href="#a{{ $a->nr }}" title="Lenke til avsnitt {{ $a->nr }}@if ($a->side), side {{ $a->side }} i originalen @endif">{{ $a->nr }}</a>
                    @if ($a->er_liste)
                        <ul>
                            @foreach ($a->setninger as $s)
                                <li><span class="setning" id="a{{ $a->nr }}-s{{ $s->nr }}">{{ $s->tekst }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <p>@foreach ($a->setninger as $s)<span class="setning" id="a{{ $a->nr }}-s{{ $s->nr }}">{{ $s->tekst }}</span>{{ $loop->last ? '' : ' ' }}@endforeach</p>
                    @endif
                    @if ($a->side && $program->kilde_url)
                        <a class="avsnitt__side" href="{{ $program->kilde_url }}#page={{ $a->side }}" rel="noopener">s. {{ $a->side }}</a>
                    @endif
                </div>
            @endforeach
        </article>

        <nav class="blaing" aria-label="Kapitler">
            @if ($forrige)<a href="{{ route('program.kapittel', [$parti, $program->slug, $forrige->slug]) }}" rel="prev">← {{ $forrige->tittel }}</a>@else<span></span>@endif
            @if ($neste)<a href="{{ route('program.kapittel', [$parti, $program->slug, $neste->slug]) }}" rel="next">{{ $neste->tittel }} →</a>@endif
        </nav>

        <details class="kapittelmeny">
            <summary>Alle kapitler</summary>
            <ol class="innholdsliste">
                @foreach ($kapitler as $kap)
                    <li><a href="{{ route('program.kapittel', [$parti, $program->slug, $kap->slug]) }}" @if ($kap->id === $kapittel->id) aria-current="page" @endif>{{ $kap->tittel }}</a></li>
                @endforeach
            </ol>
        </details>
    </div>
</x-layouts.offentlig>
