{{-- Kort forklaring av merkelappene over en oversiktstabell. Hele forklaringen står på Om-siden. --}}
<p {{ $attributes->class('legende') }}>
    @foreach (\App\Enums\Standpunktverdi::cases() as $verdi)
        <span class="merkelapp merkelapp--liten merkelapp--{{ str_replace('_', '-', $verdi->value) }}">{{ $verdi->etikett() }}</span>
    @endforeach
    <span class="merkelapp merkelapp--liten merkelapp--ikke-vurdert">Ikke kontrollert ennå</span>
    <a href="{{ route('om') }}#skalaen">Hva betyr merkelappene?</a>
</p>
