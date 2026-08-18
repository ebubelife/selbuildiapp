<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0A1B47">
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    $metaDescription = $description ?? 'Buy building materials online in Cameroon from verified suppliers — cement, roofing sheets, steel & rebar, tiles, and blocks. Track every delivery, order from abroad for diaspora-funded construction, and unlock procurement credit with Selbuildi.';
    $metaTitle = $title ?? 'Selbuildi — Building the Infrastructure of Trust';
    $canonicalUrl = $canonical ?? url()->current();
@endphp

<meta name="description" content="{{ $metaDescription }}">
<link rel="canonical" href="{{ $canonicalUrl }}">

@if ($noindex ?? false)
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow">
@endif

{{-- Open Graph / Facebook / WhatsApp --}}
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:site_name" content="Selbuildi">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:image" content="{{ $ogImage ?? asset('og-image.png') }}">
<meta property="og:locale" content="en_CM">

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $ogImage ?? asset('og-image.png') }}">

<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-180.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|sora:600,700,800&display=swap" rel="stylesheet" />

{{-- Organization structured data - present on every page --}}
<script type="application/ld+json">
{!! json_encode([
    '@@context' => 'https://schema.org',
    '@@type' => 'Organization',
    'name' => 'Selbuildi',
    'url' => url('/'),
    'logo' => asset('favicon-512.png'),
    'description' => 'Selbuildi is an online marketplace for building materials in Cameroon, connecting local and diaspora buyers with verified suppliers, order tracking, and procurement credit.',
    'areaServed' => [
        '@@type' => 'Country',
        'name' => 'Cameroon',
    ],
]) !!}
</script>

@stack('structured-data')
