{{-- Ett parti på en temaside. Se App\Visning\Partikort. --}}
@props(['kort', 'tema'])
@php
    $parti = $kort->parti;
    $kilde = $kort->kilde;
    $sporNavn = "{$tema->navn} – {$parti->navn}";
@endphp
<article class="partikort" id="parti-{{ $parti->id }}" style="--partifarge: {{ $parti->farge ?? '#6B7280' }}">
    <header class="partikort__hode">
        <h2 class="partikort__parti">{{ $parti->navn }} <abbr title="{{ $parti->navn }}">{{ $parti->forkortelse }}</abbr></h2>
        <div class="partikort__merkelapper">
            <span class="merkelapp {{ $kort->merkelappklasse() }}">{{ $kort->etikett() }}</span>
            @if ($kort->avvikverdi)
                <span class="merkelapp {{ $kort->merkelappklasse($kort->avvikverdi) }}" title="Prinsipprogrammet">Prinsipielt: {{ $kort->avvikverdi->etikett() }}</span>
            @endif
        </div>
    </header>

    @if ($kort->merknad)
        <p class="partikort__merknad">{{ $kort->merknad }}</p>
    @endif

    @if ($kilde && $kilde->utdrag !== '')
        <blockquote class="utdrag">
            <p>«{{ $kilde->utdrag }}»</p>
        </blockquote>
        <p class="kilde">
            <span>{{ $kilde->kildenavn() }}@if ($kilde->side), s. {{ $kilde->side }}@endif</span>
            @if ($kilde->lenke)
                <a href="{{ $kilde->lenke }}" data-spor-kategori="Kilde" data-spor-handling="Programlenke" data-spor-navn="{{ $kilde->sporingsnavn() }}">Les i programmet</a>
            @elseif ($kilde->pdfLenke)
                <a href="{{ $kilde->pdfLenke }}" rel="noopener" data-spor-kategori="Kilde" data-spor-handling="Programlenke" data-spor-navn="{{ $kilde->sporingsnavn() }}">Les i programmet (PDF)</a>
            @endif
        </p>
    @elseif ($kort->verdi === null)
        <p class="partikort__tom">Partiet har ikke et gjeldende prinsipprogram.</p>
    @else
        <p class="partikort__tom">Vi fant ikke noe om temaet i programmet.</p>
    @endif

    <footer class="partikort__fot">
        @if ($kort->prinsipieltSyn)
            <p class="prinsipielt">
                <span class="prinsipielt__etikett">Prinsipielt syn:</span>
                <span>{{ $kort->prinsipieltSyn }}</span>
            </p>
        @endif
        @if ($kort->sikkerhet)
            <p class="sikkerhet sikkerhet--{{ $kort->sikkerhet->value }}" title="Hvor sikker klassifiseringen er">
                <span class="sikkerhet__prikker" aria-hidden="true"><i></i><i></i><i></i></span>
                Sikkerhet: {{ mb_strtolower($kort->sikkerhet->etikett()) }}
            </p>
        @endif
    </footer>

    <details class="vismer" data-spor-kategori="Standpunkt" data-spor-handling="Åpne" data-spor-navn="{{ $sporNavn }}">
        <summary>Vis mer</summary>
        <div class="vismer__innhold">
            @if ($kort->prinsippkilde && $kort->prinsippkilde->utdrag !== '')
                <h3>Fra prinsipprogrammet</h3>
                <blockquote class="utdrag utdrag--liten"><p>«{{ $kort->prinsippkilde->utdrag }}»</p></blockquote>
                <p class="kilde">
                    <span>{{ $kort->prinsippkilde->kildenavn() }}@if ($kort->prinsippkilde->program->vedtatt_ar) (vedtatt {{ $kort->prinsippkilde->program->vedtatt_ar }})@endif</span>
                    @if ($kort->prinsippkilde->lenke)
                        <a href="{{ $kort->prinsippkilde->lenke }}" data-spor-kategori="Kilde" data-spor-handling="Programlenke" data-spor-navn="{{ $kort->prinsippkilde->sporingsnavn() }}">Les i programmet</a>
                    @endif
                </p>
            @endif

            @if ($kilde?->pdfLenke && $kilde->lenke)
                <p><a href="{{ $kilde->pdfLenke }}" rel="noopener" data-spor-kategori="Kilde" data-spor-handling="Programlenke" data-spor-navn="{{ $kilde->sporingsnavn() }} (PDF)">Se originalen (PDF{{ $kilde->side ? ", side {$kilde->side}" : '' }})</a></p>
            @endif

            <form class="meldfra" method="post" action="{{ route('tema.meld-fra', $tema) }}">
                @csrf
                <input type="hidden" name="parti_id" value="{{ $parti->id }}">
                @if ($kilde)<input type="hidden" name="standpunkt_id" value="{{ $kilde->standpunkt->id }}">@endif
                <label class="skjult-felt" aria-hidden="true">Nettside <input type="text" name="nettside" tabindex="-1" autocomplete="off"></label>
                <label for="meldfra-{{ $parti->id }}">Er dette feil? Fortell oss hva som bør rettes.</label>
                <textarea id="meldfra-{{ $parti->id }}" name="tekst" rows="3" required minlength="5" maxlength="2000"></textarea>
                <button type="submit" class="knapp" data-spor-kategori="Tilbakemelding" data-spor-handling="Meld fra om feil" data-spor-navn="{{ $sporNavn }}">Meld fra om feil</button>
            </form>
        </div>
    </details>
</article>
