<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Scorer portal'])
    </head>
    <body class="min-h-screen bg-base-100 text-base-content">
        <header class="border-b border-base-300 px-4 py-4 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold">
                    <img src="{{ asset('android-chrome-192x192.png') }}" alt="Hit the Grounds" class="size-8 object-contain" />
                    Scorer portal
                </a>
                <form method="POST" action="{{ route('company.logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">Log out</button>
                </form>
            </div>
            <nav aria-label="Scorer navigation" class="mt-4 flex flex-wrap justify-center gap-2">
                <a href="{{ route('scorer.dashboard') }}"
                   @if(request()->routeIs('scorer.dashboard')) aria-current="page" @endif
                   class="btn btn-sm {{ request()->routeIs('scorer.dashboard') ? 'btn-primary' : 'btn-ghost' }}">Dashboard</a>
                <a href="{{ route('scorer.groups.index') }}"
                   @if(request()->routeIs('scorer.groups.*')) aria-current="page" @endif
                   class="btn btn-sm {{ request()->routeIs('scorer.groups.*') ? 'btn-primary' : 'btn-ghost' }}">Group setup</a>
                <a href="{{ route('scorer.standings') }}" @if(request()->routeIs('scorer.standings')) aria-current="page" @endif
                   class="btn btn-sm {{ request()->routeIs('scorer.standings') ? 'btn-primary' : 'btn-ghost' }}">Group standings</a>
            </nav>
        </header>
        <main class="w-full p-4 sm:p-6">
            {{ $slot }}
        </main>
    </body>
</html>
