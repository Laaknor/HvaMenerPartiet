{{--
    Oversiktstabell: temaer som rader, partier som kolonner. Hver celle lenker
    til partiet på temasiden. Se App\Visning\Standpunkttabell.

    <x-standpunkttabell :tabell="$tabell" />
    Med velger: <x-standpunkttabell :tabell="$tabell" velger />
--}}
@props(['tabell', 'velger' => false])
@php use App\Visning\Standpunkttabell; @endphp
<div {{ $attributes->class('oversikt') }} data-oversikt>
    @if ($velger)
        <fieldset class="partivelger" data-partivelger hidden>
            <legend>Sammenlign partier</legend>
            <p class="partivelger__hjelp">Velg to eller flere partier for å se bare dem. Lenken til siden husker valget.</p>
            <div class="partivelger__valg">
                @foreach ($tabell->partier as $parti)
                    <label class="partivalg" style="--partifarge: {{ $parti->farge ?? '#6B7280' }}">
                        <input type="checkbox" value="{{ $parti->slug }}" data-parti-valg>
                        <span>{{ $parti->forkortelse ?: $parti->navn }}</span>
                    </label>
                @endforeach
            </div>
            <label class="partivelger__uenige"><input type="checkbox" data-bare-uenige> Bare temaer der de valgte partiene er uenige</label>
            <button type="button" class="knapp knapp--liten" data-nullstill>Vis alle partier</button>
        </fieldset>
    @endif

    <x-skalalegende />

    <div class="oversikt__rull">
        <table class="oversikt__tabell">
            <thead>
                <tr>
                    <th scope="col" class="oversikt__temakol">Tema</th>
                    @foreach ($tabell->partier as $parti)
                        <th scope="col" class="oversikt__parti" style="--partifarge: {{ $parti->farge ?? '#6B7280' }}" data-parti="{{ $parti->slug }}">
                            <abbr title="{{ $parti->navn }}">{{ $parti->forkortelse ?: $parti->navn }}</abbr>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($tabell->rader as $rad)
                    @php($tema = $rad['tema'])
                    <tr @if ($rad['gruppe']) data-gruppe="{{ $rad['gruppe'] }}" @endif @if ($rad['skjult']) hidden @endif>
                        <th scope="row" class="oversikt__tema">
                            <a href="{{ route('tema', $tema) }}">{{ $tema->navn }}</a>
                            <span class="oversikt__pastand">«{{ $tema->pastand }}»</span>
                        </th>
                        @foreach ($tabell->partier as $parti)
                            @php($kort = $rad['celler'][$parti->id] ?? null)
                            <td data-parti="{{ $parti->slug }}" data-verdi="{{ $kort?->verdi?->value ?? ($kort ? 'ingen' : 'ikke-vurdert') }}">
                                @if ($kort)
                                    <a class="celle {{ $kort->merkelappklasse() }}" href="{{ route('tema', $tema) }}#parti-{{ $parti->id }}"
                                       title="{{ $parti->navn }}: {{ $kort->etikett() }}">{{ Standpunkttabell::celletekst($kort) }}<span class="skjult"> ({{ $parti->navn }}: {{ $kort->etikett() }})</span></a>
                                @else
                                    <span class="celle celle--ikke-vurdert" title="{{ $parti->navn }}: ikke kontrollert ennå"><span class="skjult">{{ $parti->navn }}: ikke kontrollert ennå</span></span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
