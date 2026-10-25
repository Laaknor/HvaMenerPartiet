{{-- Forklaring av standpunktskalaen, lik for alle temaer. --}}
<dl {{ $attributes->class('skala') }}>
    @foreach (\App\Enums\Standpunktverdi::cases() as $verdi)
        <div>
            <dt><span class="merkelapp merkelapp--{{ str_replace('_', '-', $verdi->value) }}">{{ $verdi->etikett() }}</span></dt>
            <dd>{{ match ($verdi) {
                \App\Enums\Standpunktverdi::For => 'Programmet støtter påstanden tydelig.',
                \App\Enums\Standpunktverdi::DelvisFor => 'Støtter med vilkår eller forbehold.',
                \App\Enums\Standpunktverdi::Mot => 'Programmet går tydelig imot.',
                \App\Enums\Standpunktverdi::DeltUklart => 'Omtaler temaet, men standpunktet kan ikke leses entydig.',
                \App\Enums\Standpunktverdi::IkkeOmtalt => 'Programmet sier ingenting om temaet.',
            } }}</dd>
        </div>
    @endforeach
</dl>
