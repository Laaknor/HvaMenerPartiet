{{--
    Søkefeltet. Autofullføring mot temaene som er publisert (navn og synonymer).
    Søket slår bare opp ferdige temaer, se App\Sok\Temasok.
--}}
@props(['forslag' => [], 'verdi' => '', 'stor' => false, 'id' => 'sok'])
<form action="{{ route('sok') }}" method="get" role="search" {{ $attributes->class(['temasok', 'temasok--stor' => $stor]) }} data-temasok>
    <label for="{{ $id }}" class="{{ $stor ? 'temasok__etikett' : 'skjult' }}">Hva vil du vite hva partiene mener om?</label>
    <div class="temasok__felt">
        <svg class="temasok__ikon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        <input id="{{ $id }}" name="q" type="search" value="{{ $verdi }}" list="{{ $id }}-forslag" autocomplete="off"
               placeholder="{{ $stor ? 'For eksempel NATO, eiendomsskatt eller atomkraft' : 'Søk på tema' }}" required maxlength="200">
        <button type="submit" class="knapp knapp--primar">Søk</button>
    </div>
    <datalist id="{{ $id }}-forslag">
        @foreach ($forslag as $f)
            <option value="{{ $f }}"></option>
        @endforeach
    </datalist>
</form>
