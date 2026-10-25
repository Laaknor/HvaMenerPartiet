{{--
    Metadata for søkemotorer, LLM-er og deling i sosiale medier. Legges i <head> på alle offentlige sider:

    <x-seo tittel="NATO" beskrivelse="Hva mener partiene om NATO?" />

    Uten tittel brukes nettstedets navn og beskrivelse. :jsonld tar en liste med
    ekstra Schema.org-objekter, for eksempel et Dataset eller en BreadcrumbList.
--}}
@props([
    'tittel' => null,
    'beskrivelse' => null,
    'bilde' => null,
    'type' => 'website',
    'kanonisk' => null,
    'indekser' => true,
    'jsonld' => [],
])
@php
    $navn = config('seo.navn');
    $fullTittel = filled($tittel) ? "{$tittel} – {$navn}" : "{$navn} – hva mener partiene?";
    $beskrivelse = filled($beskrivelse) ? $beskrivelse : config('seo.beskrivelse');
    $kanonisk = $kanonisk ?? url()->current();
    $bilde = $bilde ?? config('seo.bilde');
    $bilde = filled($bilde) && ! str_starts_with($bilde, 'http') ? url($bilde) : $bilde;
    $robots = $indekser && config('seo.indekser') ? 'index, follow, max-snippet:-1, max-image-preview:large' : 'noindex, nofollow';

    $data = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $navn,
            'url' => url('/'),
            'description' => config('seo.beskrivelse'),
            'inLanguage' => 'nb-NO',
        ],
        ...array_map(fn ($objekt) => ['@context' => 'https://schema.org', ...$objekt], $jsonld),
    ];
@endphp
<title>{{ $fullTittel }}</title>
<meta name="description" content="{{ $beskrivelse }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $kanonisk }}">
<link rel="sitemap" type="application/xml" href="{{ url('sitemap.xml') }}">
<link rel="alternate" type="text/markdown" href="{{ url('llms.txt') }}" title="llms.txt">
<meta property="og:site_name" content="{{ $navn }}">
<meta property="og:locale" content="{{ config('seo.locale') }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ filled($tittel) ? $tittel : $fullTittel }}">
<meta property="og:description" content="{{ $beskrivelse }}">
<meta property="og:url" content="{{ $kanonisk }}">
@if (filled($bilde))
<meta property="og:image" content="{{ $bilde }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="{{ $bilde }}">
@else
<meta name="twitter:card" content="summary">
@endif
<meta name="twitter:title" content="{{ filled($tittel) ? $tittel : $fullTittel }}">
<meta name="twitter:description" content="{{ $beskrivelse }}">
@foreach ($data as $objekt)
<script type="application/ld+json">{!! json_encode($objekt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
