<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Bracket screen'])
    </head>
    {{-- Knockout subpage for the venue LED screen: same frame and palette as the group stage screen. --}}
    <body class="led-body min-h-screen">
        <div class="led-viewport">
            <main class="tc led-stage led-bracket" aria-labelledby="bracket-screen-heading">
                <header class="led-header">
                    <div class="led-logo">
                        <img src="{{ asset('images/htg-logo-horizontal.webp') }}" alt="Hit the Grounds {{ $year }}, powered by GTN" width="900" height="481">
                    </div>
                    <div class="led-title">
                        <span class="led-live">Live</span>
                        <h1 id="bracket-screen-heading">Knockout <span>bracket</span></h1>
                    </div>
                </header>
                @include('tournament.partials.bracket')
            </main>
        </div>
        <script>
            (() => {
                const rev = @js($rev);
                setInterval(async () => {
                    if (document.hidden) return;
                    try {
                        const response = await fetch(@js(route('tournament.rev')), { cache: 'no-store' });
                        if (response.ok && (await response.json()).rev !== rev) location.reload();
                    } catch (e) { /* Retry on the next tick after a network interruption. */ }
                }, 10000);
            })();
        </script>
    </body>
</html>
