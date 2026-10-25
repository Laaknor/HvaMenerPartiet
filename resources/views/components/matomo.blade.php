{{--
    Matomo-sporing for den offentlige siden. Skal ikke brukes i admin.

    Vanlig side:      <x-matomo />
    Søkeresultatside: <x-matomo :sok="$sokeord" sokekategori="Tema" :treff="$antallTreff" />
    (Matomo vil ha trackSiteSearch i stedet for trackPageView på søkesider.)

    Hendelser merkes i HTML-en med data-spor-*-attributter, se README.
--}}
@props(['sok' => null, 'sokekategori' => null, 'treff' => null])

@php
    $host = trim(preg_replace('#^(https?:)?//#i', '', (string) config('services.matomo.host')), '/');
    $siteId = trim((string) config('services.matomo.site_id'));
@endphp

@if ($host !== '' && $siteId !== '')
<!-- Matomo -->
<script>
  var _paq = window._paq = window._paq || [];
  /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
@if (filled($sok))
  _paq.push(['trackSiteSearch', @js((string) $sok), @js(filled($sokekategori) ? (string) $sokekategori : false), @js($treff === null ? false : (int) $treff)]);
@else
  _paq.push(['trackPageView']);
@endif
  _paq.push(['enableLinkTracking']);
  (function() {
    var u=@js('//'.$host.'/');
    _paq.push(['setTrackerUrl', u+'matomo.php']);
    _paq.push(['setSiteId', @js($siteId)]);
    var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
  })();

  // Hjelpere for hendelser og søk som skjer uten ny sidelasting (f.eks. Livewire).
  (function() {
    var hmp = window.HvaMenerPartiet = window.HvaMenerPartiet || {};

    hmp.spor = function(kategori, handling, navn, verdi) {
      var hendelse = ['trackEvent', kategori, handling];
      if (navn) hendelse.push(navn);
      if (navn && verdi !== undefined && verdi !== null && verdi !== '' && !isNaN(verdi)) hendelse.push(Number(verdi));
      _paq.push(hendelse);
    };

    hmp.sporSok = function(sokeord, kategori, treff) {
      if (!sokeord) return;
      _paq.push(['setCustomUrl', window.location.href]);
      _paq.push(['setDocumentTitle', document.title]);
      _paq.push(['trackSiteSearch', String(sokeord), kategori || false, (treff === undefined || treff === null) ? false : Number(treff)]);
    };

    function fraElement(el) {
      hmp.spor(
        el.getAttribute('data-spor-kategori') || 'Interaksjon',
        el.getAttribute('data-spor-handling'),
        el.getAttribute('data-spor-navn'),
        el.getAttribute('data-spor-verdi')
      );
    }

    // Klikk på lenker og knapper merket med data-spor-handling.
    document.addEventListener('click', function(e) {
      var el = e.target.closest && e.target.closest('[data-spor-handling]');
      if (el && el.tagName !== 'DETAILS') fraElement(el);
    });

    // <details data-spor-handling="..."> spores når den åpnes, ikke når den lukkes.
    document.addEventListener('toggle', function(e) {
      var el = e.target;
      if (el.tagName === 'DETAILS' && el.open && el.hasAttribute('data-spor-handling')) fraElement(el);
    }, true);
  })();
</script>
<!-- End Matomo Code -->
@endif
