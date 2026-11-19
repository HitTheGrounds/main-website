<x-layouts.public title="Tournament" description="Match results, group standings, knockout bracket and player leaders for Hit the Grounds.">
    {{-- Same-page crossfade between tournament views. Both pages must opt in, so
         this only runs on tournament-to-tournament navigation; browsers without
         support just load the page. --}}
    <style>
        @view-transition { navigation: auto; }
        ::view-transition-group(tc-status-pill) { animation-duration: .38s; animation-timing-function: cubic-bezier(.22, 1, .36, 1); }
        ::view-transition-old(tc-match-list) { animation: .18s cubic-bezier(.22, 1, .36, 1) both tc-vt-out; }
        ::view-transition-new(tc-match-list) { animation: .32s cubic-bezier(.22, 1, .36, 1) both tc-vt-in; }
        @keyframes tc-vt-out { to { opacity: 0; } }
        @keyframes tc-vt-in { from { opacity: 0; transform: translateY(8px); } }
        @media (prefers-reduced-motion: reduce) { @view-transition { navigation: none; } }
    </style>
    <div class="tc">
        <div class="tc-masthead">
        <header class="tc-hero">
            <p class="tc-eyebrow">Hit the Grounds {{ $year }}</p>
            <h1>Tournament <span>centre.</span></h1>
        </header>

        <nav aria-label="Tournament sections" class="tc-nav">
            @foreach ($tabs as $key => $section)
                <a href="{{ route('tournament.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif>
                    <svg class="tc-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @if ($key === 'matches')
                            <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-13 5h2m4 0h2"/>
                        @elseif ($key === 'table')
                            <path d="M4 5h16M4 12h16M4 19h16M8 5v14m8-14v14"/>
                        @elseif ($key === 'bracket')
                            <path d="M3 3v6h5V6h8v3h5V3M3 15v6h5v-3h8v3h5v-6M12 6v12"/>
                        @else
                            <path d="M5 20v-6h4v6m2 0V9h4v11m2 0V4h4v16M3 20h19"/>
                        @endif
                    </svg>
                    {{ $section['label'] }}
                </a>
            @endforeach
        </nav>
        </div>

        <section aria-labelledby="tournament-section-heading" class="tc-section-head">
            <h2 id="tournament-section-heading">{{ $tabs[$tab]['label'] }}</h2>
            <p class="sr-only">{{ $tabs[$tab]['description'] }}</p>
        </section>
        @if ($tab === 'matches')
            @include('tournament.partials.matches')
        @elseif ($tab === 'table')
            @include('tournament.partials.standings')
        @elseif ($tab === 'bracket')
            @include('tournament.partials.bracket')
        @elseif ($tab === 'stats')
            @include('tournament.partials.stats')
        @endif
        @if (! $filterErrors)
            <footer class="tc-publication">
                <p>This page updates automatically when new results are published.</p>
            </footer>
        @endif
    </div>
    @if (! $filterErrors)
        <script>
            (() => {
                let rev = @js($rev);
                setInterval(async () => {
                    if (document.hidden) return;
                    try {
                        const next = (await (await fetch(@js(route('tournament.rev')))).json()).rev;
                        if (rev !== null && next !== rev) {
                            const html = await (await fetch(location.href)).text();
                            const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('.tc');
                            if (fresh) document.querySelector('.tc').replaceWith(fresh);
                        }
                        rev = next;
                    } catch (e) { /* network blip: try again next tick */ }
                }, 10000);
            })();
        </script>
    @endif
</x-layouts.public>
