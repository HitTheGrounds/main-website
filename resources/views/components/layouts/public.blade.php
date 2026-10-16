@props(['title' => null, 'description' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-base-100 text-base-content antialiased overflow-x-clip">
    <!-- Navigation Header -->
    <x-Navbar />

    <!-- Main Content -->
    <main id="public-page-content" class="min-h-screen">
        {{ $slot }}
    </main>

    <!-- Footer -->
    <x-FooterSection />
    <x-page-scrollbar />
    @livewireScripts
</body>

</html>
