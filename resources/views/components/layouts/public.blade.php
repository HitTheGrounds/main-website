@props(['title' => null, 'description' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-base-100 text-base-content antialiased overflow-x-clip">
    @if (request()->routeIs('tournament.index'))
        <a href="#public-page-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-box focus:bg-base-100 focus:p-3 focus:outline-2 focus:outline-primary">Skip to tournament content</a>
    @endif
    <!-- Navigation Header -->
    <x-Navbar />

    <!-- Main Content -->
    <main id="public-page-content" @if(request()->routeIs('tournament.index')) tabindex="-1" @endif class="min-h-screen">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <x-FooterSection />
    <x-page-scrollbar />
    @livewireScripts
</body>

</html>
