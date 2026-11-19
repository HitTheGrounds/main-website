<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Scorer portal'])
        {{-- Livewire's base styles (hide wire:loading until a request runs). The
             layout loads @livewireScripts by hand, which turns off auto-injection. --}}
        @livewireStyles
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
        @if (session('scorer_toast'))
            {{-- One-off success toast after a redirect (for example, a published result). --}}
            <div class="scorer-toast" role="status" aria-live="polite"
                 x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 7000)" x-transition.opacity.duration.300ms>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                <p>{{ session('scorer_toast') }}</p>
                <button type="button" x-on:click="show = false" aria-label="Dismiss">&times;</button>
            </div>
        @endif
        @livewireScripts
        <script>
            // Scorer pages: after any Livewire update, bring a new error message into
            // view if it rendered off screen (for example, above a long form).
            document.addEventListener('livewire:init', () => {
                Livewire.hook('commit', ({ succeed }) => {
                    succeed(() => requestAnimationFrame(() => {
                        const openDialog = document.querySelector('dialog[open]');
                        const alert = (openDialog ?? document).querySelector('[role="alert"].alert-error');
                        if (!alert) return;
                        const box = alert.getBoundingClientRect();
                        if (box.top >= 0 && box.bottom <= window.innerHeight) return;
                        alert.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
                    }));
                });
            });
        </script>
    </body>
</html>
