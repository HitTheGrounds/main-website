<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    $siteName = config('app.name');
    $pageTitle = isset($title) && $title ? $title . ' | Hit the Grounds 2026' : $siteName . ' 2026';
    $pageDescription = $description ?? 'Hit the Grounds 2026: the annual cricket tournament of the Department of Computer Science and Engineering, University of Moratuwa. University teams and industry teams compete in a one-day showcase of skill and team spirit.';
    $pageUrl = url()->current();
    // Versioned file name, so chat apps and search engines do not keep showing a cached older image
    $pageImage = asset('og-cover-2.png');
    $pageImageAlt = 'Hit the Grounds 2026 logo on a cream background';
@endphp

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
<meta name="keywords" content="cricket tournament, HTG, hit the grounds, CSE tournament, university cricket, cricket competition, student sports, faculty cricket, alumni cricket, campus sports, team spirit, sportsmanship">
<meta name="author" content="HTG Tournament">
<link rel="canonical" href="{{ $pageUrl }}">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="{{ $pageUrl }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:image" content="{{ $pageImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $pageImageAlt }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="en_US">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:url" content="{{ $pageUrl }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
<meta name="twitter:image" content="{{ $pageImage }}">
<meta name="twitter:image:alt" content="{{ $pageImageAlt }}">

<!-- Additional SEO -->
<meta name="robots" content="index, follow">
<meta name="googlebot" content="index, follow">
<meta name="theme-color" content="#F4F1EA">

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Google Analytics: production only, and not for the admin area, so our own visits do not skew the numbers --}}
@if (app()->environment('production') && config('services.google_analytics.id') && ! request()->is('admin/*'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ config('services.google_analytics.id') }}');
    </script>
@endif

{{-- EasyMDE for Markdown Editor --}}
<link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
<script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
