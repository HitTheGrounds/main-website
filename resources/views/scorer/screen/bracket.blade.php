<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Bracket screen'])
    </head>
    <body class="min-h-screen bg-base-100 text-base-content">
        <div class="led-viewport">
            <main class="tc led-stage led-bracket" aria-labelledby="bracket-screen-heading">
                <header class="flex shrink-0 items-center justify-between border-b-2 border-primary pb-[.8cqh]">
                    <img src="{{ asset('images/htg-logo-horizontal.webp') }}" alt="Hit the Grounds {{ $year }}, powered by GTN" width="900" height="481" class="h-[6cqh] w-auto">
                    <h1 id="bracket-screen-heading" class="text-[3.4cqh] font-extrabold uppercase text-primary-ink">Knockout bracket</h1>
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
