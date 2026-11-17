<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Scorer portal'])
    </head>
    <body class="scorer-workspace min-h-screen bg-base-100 text-base-content">
        <a href="#scorer-main" class="scorer-skip">Skip to scorer tools</a>
        <aside class="scorer-rail">
            <div class="scorer-brand">
                <a href="{{ route('scorer.dashboard') }}" class="flex min-w-0 items-center gap-3">
                    <img src="{{ asset('android-chrome-192x192.png') }}" alt="" width="36" height="36" class="rounded-lg">
                    <span><strong class="block">Scorer portal</strong><small>Hit the Grounds {{ config('tournament.year') }}</small></span>
                </a>
            </div>
            <nav aria-label="Scorer navigation" class="scorer-nav">
                @foreach([
                    ['scorer.dashboard', ['scorer.dashboard', 'scorer.matches.*'], 'Matches'],
                    ['scorer.groups.index', ['scorer.groups.*'], 'Groups'],
                    ['scorer.standings', ['scorer.standings'], 'Standings'],
                    ['scorer.knockouts', ['scorer.knockouts'], 'Knockouts'],
                ] as [$route, $patterns, $label])
                    <a href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif
                       @class(['scorer-nav-link', 'is-active' => request()->routeIs(...$patterns)])>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="scorer-account">
                @if (config('tournament.enabled') === true && Route::has('tournament.index'))
                    <a href="{{ route('tournament.index') }}">Public results <span aria-hidden="true">↗</span></a>
                @endif
                <form method="POST" action="{{ route('company.logout') }}">
                    @csrf
                    <button type="submit">Log out</button>
                </form>
            </div>
        </aside>
        <main id="scorer-main" tabindex="-1" class="scorer-main">
            {{ $slot }}
        </main>
        @livewireScripts
    </body>
</html>
