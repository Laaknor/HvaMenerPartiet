<x-layouts.offentlig :tittel="$parti->navn.': '.$program->tittel" :beskrivelse="'Hele '.$program->tittel.' fra '.$parti->navn.', digitalisert med lenke til hver setning.'">
    <div class="ramme" style="--partifarge: {{ $parti->farge ?? '#6B7280' }}">
        <nav class="sti" aria-label="Brødsmuler">
            <a href="{{ route('programmer') }}">Programmene</a> <span aria-hidden="true">/</span> <span>{{ $parti->navn }}</span>
        </nav>
        <header class="programhode">
            <p class="programhode__parti">{{ $parti->navn }}</p>
            <h1>{{ $program->tittel }}</h1>
            <p class="dempet">
                {{ $program->type->etikett() }}@if ($program->vedtatt_ar), vedtatt {{ $program->vedtatt_ar }}@endif.
                @if ($program->kilde_url)<a href="{{ $program->kilde_url }}" rel="noopener" data-spor-kategori="Kilde" data-spor-handling="Programlenke" data-spor-navn="{{ $parti->navn }} {{ $program->tittel }} (PDF)">Originalen (PDF)</a>@endif
            </p>
        </header>
        <h2 class="seksjon__tittel">Innhold</h2>
        <ol class="innholdsliste">
            @foreach ($kapitler as $kap)
                <li><a href="{{ route('program.kapittel', [$parti, $program->slug, $kap->slug]) }}">{{ $kap->tittel }}</a>@if ($kap->side) <span class="dempet">s. {{ $kap->side }}</span>@endif</li>
            @endforeach
        </ol>
    </div>
</x-layouts.offentlig>
