{{--
    Ett parti på en temaside, som en kort rad: partinavn, merkelapp og første
    setning av sitatet. Resten (hele sitatet, kilde, prinsipielt syn,
    sikkerhet og «Meld fra om feil») ligger bak raden. Se App\Visning\Partikort.
--}}
@props(['kort', 'tema'])
@php
    $parti = $kort->parti;
    $kilde = $kort->kilde;
    $sporNavn = "{$tema->navn} – {$parti->navn}";
    $lav = $kort->sikkerhet === \App\Enums\Sikkerhet::Lav;
@endphp
<details class="partirad" id="parti-{{ $parti->id }}" style="--partifarge: {{ $parti->farge ?? '#6B7280' }}"
         data-spor-kategori="Standpunkt" data-spor-handling="Åpne" data-spor-navn="{{ $sporNavn }}">
    <summary class="partirad__sammendrag">
        <span class="partirad__hode">
            <span class="partirad__parti">{{ $parti->navn }}</span>
            <span class="skjult">{{ $kort->etikett() }}</span>
            @if ($kort->avvikverdi)
                <span class="merkelapp merkelapp--liten {{ $kort->merkelappklasse($kort->avvikverdi) }}" title="{{ \App\Analyse\Visningsregel::MERKNAD_AVVIK }}">Prinsipielt: {{ $kort->avvikverdi->etikett() }}</span>
            @endif
            @if ($kort->merknad === \App\Analyse\Visningsregel::MERKNAD_FRA_PRINSIPP)
                <span class="partirad__notis">Fra prinsipprogrammet</span>
            @endif
            @if ($lav)
                <span class="partirad__notis partirad__notis--varsel" title="Teksten kan leses på flere måter. Les sitatet selv.">Usikker tolkning</span>
            @endif
        </span>
        <span class="partirad__sitat">
            @if ($kilde && $kilde->utdrag !== '')
                «{{ $kilde->kort() }}»
            @elseif ($kort->verdi === null)
                <em>Partiet har ikke et gjeldende prinsipprogram.</em>
            @else
                <em>Vi fant ikke noe om temaet i programmet.</em>
            @endif
        </span>
        <span class="partirad__mer" aria-hidden="true">{{ $kilde && $kilde->utdrag !== '' ? 'Hele sitatet' : 'Mer' }}</span>
    </summary>

    <div class="partirad__innhold">
        @if ($kort->merknad)
            <p class="partirad__merknad">{{ $kort->merknad }}</p>
        @endif

        @if ($kilde && $kilde->utdrag !== '')
            <blockquote class="utdrag"><p>«{{ $kilde->utdrag }}»</p></blockquote>
            <p class="kilde">
                <span>{{ $kilde->kildenavn() }}@if ($kilde->side), s. {{ $kilde->side }}@endif</span>
                @if ($kilde->lenke)
                    <a href="{{ $kilde->lenke }}" data-spor-kategori="Kilde" data-spor-handling="Programlenke" data-spor-navn="{{ $kilde->sporingsnavn() }}">Les i programmet</a>
                @elseif ($kilde->pdfLenke)
                    <a href="{{ $kilde->pdfLenke }}" rel="noopener" data-spor-kategori="Kilde" data-spor-handling="Programlenke" data-spor-navn="{{ $kilde->sporingsnavn() }}">Les i programmet (PDF)</a>
                @endif
                @if ($kilde->pdfLenke && $kilde->lenke)
                    <a href="{{ $kilde->pdfLenke }}" rel="noopener" data-spor-kategori="Kilde" data-spor-handling="Programlenke" data-spor-navn="{{ $kilde->sporingsnavn() }} (PDF)">Originalen (PDF{{ $kilde->side ? ", s. {$kilde->side}" : '' }})</a>
                @endif
            </p>
        @endif

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

        <p class="partirad__fakta">
            @if ($kort->prinsipieltSyn)
                <span><span class="prinsipielt__etikett">Prinsipielt syn:</span> {{ $kort->prinsipieltSyn }}</span>
            @endif
            @if ($kort->sikkerhet)
                <span class="sikkerhet sikkerhet--{{ $kort->sikkerhet->value }}" title="Hvor sikker klassifiseringen er">
                    <span class="sikkerhet__prikker" aria-hidden="true"><i></i><i></i><i></i></span>
                    Sikkerhet: {{ mb_strtolower($kort->sikkerhet->etikett()) }}
                </span>
            @endif
            <a href="{{ route('parti', $parti) }}">Alt {{ $parti->forkortelse ?: $parti->navn }} mener</a>
        </p>

        <details class="meldfra-boks">
        <summary>Er dette feil? Meld fra</summary>
        <form class="meldfra" method="post" action="{{ route('tema.meld-fra', $tema) }}">
            @csrf
            <input type="hidden" name="parti_id" value="{{ $parti->id }}">
            @if ($kilde)<input type="hidden" name="standpunkt_id" value="{{ $kilde->standpunkt->id }}">@endif
            <label class="skjult-felt" aria-hidden="true">Nettside <input type="text" name="nettside" tabindex="-1" autocomplete="off"></label>
            <label for="meldfra-{{ $parti->id }}">Er dette feil? Fortell oss hva som bør rettes.</label>
            <textarea id="meldfra-{{ $parti->id }}" name="tekst" rows="3" required minlength="5" maxlength="2000"></textarea>
            <button type="submit" class="knapp" data-spor-kategori="Tilbakemelding" data-spor-handling="Meld fra om feil" data-spor-navn="{{ $sporNavn }}">Meld fra om feil</button>
        </form>
        </details>
    </div>
</details>
