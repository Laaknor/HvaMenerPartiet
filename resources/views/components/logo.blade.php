{{-- Logoen: en snakkeboble med søyler av ulik lengde, ett syn per parti. Fargene følger temaet (lyst/mørkt). --}}
@props(['ordmerke' => true])
<span {{ $attributes->class('logo') }}>
    <svg class="logo__merke" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <path class="logo__boble" d="M16 6H48A12 12 0 0 1 60 18V36A12 12 0 0 1 48 48H29L16 59V48A12 12 0 0 1 4 36V18A12 12 0 0 1 16 6Z"/>
        <rect class="logo__soyle" x="14" y="15" width="36" height="6" rx="3"/>
        <rect class="logo__soyle logo__soyle--aksent" x="14" y="24" width="22" height="6" rx="3"/>
        <rect class="logo__soyle logo__soyle--dempet" x="14" y="33" width="29" height="6" rx="3"/>
    </svg>
    @if ($ordmerke)
        <span class="logo__tekst">HvaMener<span class="logo__aksent">Partiet</span><span class="logo__domene">.no</span></span>
    @endif
</span>
