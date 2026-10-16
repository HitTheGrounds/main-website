<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-base-100 text-base-content antialiased pb-24 lg:pb-0 overflow-x-hidden">
    <!-- Navigation Header -->
    <x-Navbar />

    <!-- Main Content -->
    <main class="min-h-screen">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <x-FooterSection />
    <x-BackToTop />
    @livewireScripts
</body>

</html>
