@php
    // 'dark' marks a logo that only exists in a white version, so it sits on a dark tile.
    $logos = [
        'GTN' => ['/images/rankings/gtn.avif'],
        'Synopsys' => ['/images/rankings/synopsys.avif'],
        'Codegen' => ['/partner/codegen.avif'],
        'Virtusa' => ['/images/rankings/virtusa.avif'],
        'SimCentric Technologies' => ['/images/rankings/simcentric.avif'],
        'DirectFN' => ['/images/rankings/directfn.avif', true],
        '99X' => ['/images/rankings/99x.avif'],
        'WSO2' => ['/images/rankings/wso2.avif'],
    ];

    // Newest first.
    $seasons = [
        2025 => ['GTN', 'Synopsys'],
        2024 => ['Synopsys', 'Codegen'],
        2019 => ['Virtusa', 'SimCentric Technologies'],
        2018 => ['DirectFN', '99X'],
        2015 => ['WSO2', 'Virtusa'],
    ];

    $champions = collect($seasons)->map(fn ($pair) => $pair[0])->all();
    $board = collect($seasons)->flatMap(fn ($pair, $year) => [
        ['team' => $pair[0], 'year' => $year, 'role' => 'champion'],
        ['team' => $pair[1], 'year' => $year, 'role' => 'runner'],
    ])->groupBy('team')->map(fn ($rows, $team) => [
        'team' => $team,
        'logo' => $logos[$team][0],
        'dark' => $logos[$team][1] ?? false,
        'total' => $rows->count(),
        'champion' => $rows->where('role', 'champion')->count(),
        'runner' => $rows->where('role', 'runner')->count(),
        'latest' => $rows->max('year'),
    ])->sort(fn ($a, $b) => [$b['total'], $b['champion'], $b['latest']] <=> [$a['total'], $a['champion'], $a['latest']])->values();

    $scene = collect($seasons)->map(function ($pair, $year) use ($logos) {
        [$champion, $runner] = $pair;
        return [
            'year' => $year,
            'champion' => $champion, 'championLogo' => $logos[$champion][0], 'championDark' => $logos[$champion][1] ?? false,
            'runner' => $runner, 'runnerLogo' => $logos[$runner][0], 'runnerDark' => $logos[$runner][1] ?? false,
        ];
    })->values();
@endphp

<x-layouts.public title="Past Seasons" description="Champions and runners-up from every past season of Hit the Grounds, from 2015 to 2025, with an all-time leaderboard.">
    {{-- Title --}}
    <section class="bg-base-100">
        <x-PageContainer class="!pb-6">
            <h1 class="text-center font-heading text-[clamp(2.75rem,8vw,5.5rem)]" data-aos="fade-up">
                <span class="text-base-content">Past </span><span class="text-primary">Seasons</span>
            </h1>
        </x-PageContainer>
    </section>

    {{-- Champions ticker --}}
    <div class="overflow-hidden whitespace-nowrap bg-primary py-4 text-secondary" aria-hidden="true">
        <div class="aw-marquee">
            @foreach ([1, 2] as $copy)
                <span class="inline-flex items-center gap-9 pr-9 text-xl font-extrabold uppercase tracking-wider">
                    @foreach (array_reverse($champions, true) as $year => $name)
                        <span>{{ $year }}</span><span class="opacity-60">{{ $name }}</span><span class="opacity-45">&#9679;</span>
                    @endforeach
                </span>
            @endforeach
        </div>
    </div>

    {{-- Rotating podium --}}
    <section class="bg-base-100">
        <x-PageContainer class="!pt-10">
            <div class="ps-stage relative" data-podium data-seasons='@json($scene)' aria-label="Past season podiums">
                <div class="ps-viewport relative overflow-hidden rounded-[2rem] bg-[#0E0B09] shadow-[0_30px_60px_-30px_rgb(31_27_22_/_.6)]">
                    <div data-stage3d class="ps-3d absolute inset-0 z-0 hidden" style="background: radial-gradient(80% 65% at 50% 42%, #3a2113 0%, #14100d 60%, #0b0907 100%)"></div>
                    <div data-overlay class="pointer-events-none absolute left-5 top-5 z-10 hidden sm:left-9 sm:top-8">
                        <p class="text-xs font-bold uppercase tracking-[0.3em] text-base-100/60 sm:text-sm">Hit the Grounds</p>
                        <p data-overlay-year class="font-heading text-5xl leading-none text-base-100 sm:text-7xl">{{ array_key_first($seasons) }}</p>
                        <span data-overlay-badge class="mt-3 inline-flex items-center gap-2 rounded-full bg-primary px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-primary-content">
                            <span class="aw-pulse-dot size-2 rounded-full bg-primary-content"></span>Latest season
                        </span>
                    </div>
                    <p class="pointer-events-none absolute right-6 top-6 z-10 hidden text-right text-[11px] font-bold uppercase tracking-[0.2em] text-base-100/45 sm:block ps-hint" aria-hidden="true">Drag to spin the stage</p>
                    @foreach ($seasons as $year => [$champion, $runnerUp])
                        @php
                            [$champSrc, $champDark] = $logos[$champion] + [1 => false];
                            [$runSrc, $runDark] = $logos[$runnerUp] + [1 => false];
                        @endphp
                        <div class="ps-slide {{ $loop->first ? 'is-active' : '' }}" data-slide role="group" aria-roledescription="slide" aria-label="{{ $year }}: champions {{ $champion }}, runners-up {{ $runnerUp }}">
                            <div class="ps-spot" aria-hidden="true"></div>
                            <div class="ps-floor" aria-hidden="true"></div>
                            @foreach ([8, 22, 36, 58, 72, 86, 94] as $k => $left)
                                <span class="aw-spark" style="left: {{ $left }}%; bottom: {{ 30 + ($k % 3) * 8 }}%; animation-delay: {{ $k * 0.9 }}s; {{ $k % 2 ? 'width: 6px; height: 6px;' : '' }}" aria-hidden="true"></span>
                            @endforeach

                            <div class="absolute left-5 top-5 z-10 sm:left-9 sm:top-8">
                                <p class="text-xs font-bold uppercase tracking-[0.3em] text-base-100/60 sm:text-sm">Hit the Grounds</p>
                                <h2 class="font-heading text-5xl leading-none text-base-100 sm:text-7xl">{{ $year }}</h2>
                                @if ($loop->first)
                                    <span class="mt-3 inline-flex items-center gap-2 rounded-full bg-primary px-3 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-primary-content">
                                        <span class="aw-pulse-dot size-2 rounded-full bg-primary-content"></span>Latest season
                                    </span>
                                @endif
                            </div>

                            <div class="ps-set">
                                {{-- 1st runners-up, left --}}
                                <div class="ps-col ps-col-run">
                                    <div class="ps-card ps-card-run {{ $runDark ? 'is-dark' : '' }}">
                                        <img src="{{ $runSrc }}" alt="{{ $runnerUp }} logo" loading="lazy">
                                    </div>
                                    <div class="ps-podium">
                                        <span class="ps-top" aria-hidden="true"></span>
                                        <div class="ps-face">
                                            <span class="ps-label"><x-laurel />1st runner up<x-laurel flip /></span>
                                            <span class="ps-name">{{ $runnerUp }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Champions, centre and higher --}}
                                <div class="ps-col ps-col-champ">
                                    <img src="/images/crown.avif" alt="" aria-hidden="true" width="668" height="775" class="ps-crown">
                                    <div class="ps-card ps-card-champ {{ $champDark ? 'is-dark' : '' }}">
                                        <img src="{{ $champSrc }}" alt="{{ $champion }} logo" loading="lazy">
                                    </div>
                                    <div class="ps-podium ps-podium-champ">
                                        <span class="ps-top" aria-hidden="true"></span>
                                        <div class="ps-face">
                                            <span class="ps-label ps-label-gold"><x-laurel />Champion<x-laurel flip /></span>
                                            <span class="ps-name">{{ $champion }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <button type="button" class="ps-nav ps-prev" data-prev aria-label="Previous season">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                    </button>
                    <button type="button" class="ps-nav ps-next" data-next aria-label="Next season">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                    </button>
                    <div class="ps-progress" aria-hidden="true"><span data-progress></span></div>
                </div>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-2 sm:gap-3" role="tablist" aria-label="Choose a season">
                    @foreach ($seasons as $year => $pair)
                        <button type="button" role="tab" data-tab class="ps-tab {{ $loop->first ? 'is-active' : '' }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $year }}</button>
                    @endforeach
                </div>
            </div>
        </x-PageContainer>
    </section>

    {{-- Leaderboard: appearances on the podium, as tally marks and numbers --}}
    <section class="bg-base-200">
        <x-PageContainer>
            <div class="mb-10 text-center" data-aos="fade-up">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">All time</p>
                <h2 class="mt-3 font-heading text-[clamp(2.2rem,5vw,3.6rem)] text-base-content">Who keeps making the podium</h2>
                <p class="mx-auto mt-3 max-w-xl text-base-content/70">Every time a company finishes as champion or runner-up, it earns a mark.</p>
            </div>

            <div class="ps-board mx-auto max-w-5xl overflow-hidden rounded-[2rem] bg-[#17120E] text-base-100 shadow-[0_30px_60px_-30px_rgb(31_27_22_/_.55)]" data-board data-aos="fade-up">
                <div class="hidden grid-cols-[3.5rem_minmax(0,1.3fr)_minmax(0,1.5fr)_5.5rem] items-center gap-4 border-b border-white/10 px-6 py-4 text-[11px] font-bold uppercase tracking-[0.16em] text-base-100/50 md:grid">
                    <span>#</span><span>Company</span><span>Tally</span><span class="text-center">Total</span>
                </div>

                @foreach ($board as $i => $row)
                    <div class="ps-row grid grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-x-4 gap-y-3 border-b border-white/[.07] px-4 py-5 last:border-b-0 sm:px-6 md:grid-cols-[3.5rem_minmax(0,1.3fr)_minmax(0,1.5fr)_5.5rem]" style="--i: {{ $i }}">
                        <span class="font-heading text-2xl text-primary sm:text-3xl">{{ $i + 1 }}</span>

                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-11 w-20 shrink-0 place-items-center rounded-xl px-2 sm:h-12 sm:w-24 {{ $row['dark'] ? 'bg-[#1F2A44]' : 'bg-[#FBFAF6]' }}">
                                <img src="{{ $row['logo'] }}" alt="" class="max-h-8 w-auto max-w-full object-contain sm:max-h-9" loading="lazy">
                            </span>
                            <span class="min-w-0 font-heading text-lg leading-tight sm:text-xl">{{ $row['team'] }}</span>
                        </div>

                        <span class="order-last col-span-3 flex items-center md:order-none md:col-span-1" aria-hidden="true">
                            @php
                                $groups = intdiv($row['total'], 5);
                                $rest = $row['total'] % 5;
                                $n = 0;
                            @endphp
                            <svg class="ps-tally h-12 w-full max-w-[17rem]" preserveAspectRatio="xMinYMid meet" viewBox="0 0 {{ max(1, $groups) * 66 + ($rest ? $rest * 13 + 8 : 0) + 4 }} 56" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round">
                                @for ($g = 0; $g < $groups; $g++)
                                    @foreach ([8, 21, 34, 47] as $x)
                                        <path pathLength="1" class="ps-mark" style="--m: {{ $n++ }}" d="M{{ $g * 66 + $x }} 6 V50"/>
                                    @endforeach
                                    <path pathLength="1" class="ps-mark ps-slash" style="--m: {{ $n++ }}" d="M{{ $g * 66 + 1 }} 42 L{{ $g * 66 + 56 }} 14"/>
                                @endfor
                                @for ($r = 0; $r < $rest; $r++)
                                    <path pathLength="1" class="ps-mark" style="--m: {{ $n++ }}" d="M{{ $groups * 66 + 8 + $r * 13 }} 6 V50"/>
                                @endfor
                            </svg>
                        </span>

                        <span class="text-center font-heading text-4xl leading-none sm:text-5xl md:text-center">
                            <span data-count="{{ $row['total'] }}">{{ $row['total'] }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </x-PageContainer>
    </section>

    {{-- Call to action --}}
    <section class="bg-base-100">
        <x-PageContainer class="!pt-10">
            <div class="relative flex flex-col items-center justify-center gap-7 overflow-hidden rounded-[2.25rem] bg-[#17120E] px-7 py-12 text-center text-base-100 sm:px-12" data-aos="fade-up">
                <div class="aw-glow pointer-events-none absolute -right-20 -top-28 size-[480px] rounded-full" aria-hidden="true"
                     style="background: radial-gradient(closest-side, rgb(255 106 19 / .55), transparent)"></div>
                <h2 class="relative font-heading text-[clamp(2.2rem,5vw,3.6rem)]">Your name could be next</h2>
                <a href="{{ route('register') }}"
                   class="relative inline-flex shrink-0 items-center gap-3.5 rounded-full bg-primary py-2 pl-7 pr-2 text-[17px] font-bold text-secondary transition hover:-translate-y-0.5 hover:bg-[#ff7a2b]">
                    Register Your Team
                    <span class="grid size-11 place-items-center rounded-full bg-secondary text-secondary-content">
                        <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </span>
                </a>
            </div>
        </x-PageContainer>
    </section>

    <script>
        (() => {
            const board = document.querySelector('[data-board]');
            if (!board) return;
            board.classList.add('is-armed');
            const play = () => {
                board.classList.add('is-in');
                if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                board.querySelectorAll('[data-count]').forEach((el, k) => {
                    const end = +el.dataset.count;
                    el.textContent = '0';
                    const row = el.closest('.ps-row');
                    const start = performance.now() + (+row.style.getPropertyValue('--i') * 180) + 250;
                    const tick = (now) => {
                        const t = Math.min(1, Math.max(0, (now - start) / (end * 330)));
                        el.textContent = String(Math.round(end * t));
                        if (t < 1) requestAnimationFrame(tick);
                    };
                    requestAnimationFrame(tick);
                });
            };
            new IntersectionObserver((entries, io) => {
                if (entries.some((e) => e.isIntersecting)) { play(); io.disconnect(); }
            }, { threshold: 0.25 }).observe(board);
        })();
    </script>

    <script>
        (() => {
            const root = document.querySelector('[data-podium]');
            if (!root) return;
            // With WebGL the 3D stage (past-seasons-3d.js) takes over; this is the flat fallback.
            try {
                const c = document.createElement('canvas');
                if (window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl'))) { root.classList.add('wants-3d'); return; }
            } catch (e) { /* no WebGL */ }
            const slides = [...root.querySelectorAll('[data-slide]')];
            const tabs = [...root.querySelectorAll('[data-tab]')];
            const bar = root.querySelector('[data-progress]');
            const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
            const DELAY = 6500;
            let i = 0, timer = null, held = false;
            root.classList.add('is-ready');

            const show = (n, dir = 1) => {
                const next = (n + slides.length) % slides.length;
                if (next === i) return;
                const out = slides[i];
                out.classList.remove('is-active');
                out.classList.add(dir > 0 ? 'is-out-left' : 'is-out-right');
                setTimeout(() => out.classList.remove('is-out-left', 'is-out-right'), 1000);
                slides[next].classList.add('is-active');
                tabs.forEach((t, k) => { t.classList.toggle('is-active', k === next); t.setAttribute('aria-selected', k === next); });
                i = next;
                restart();
            };

            const restart = () => {
                clearTimeout(timer);
                bar.style.animation = 'none'; void bar.offsetWidth;
                if (still || held) return;
                bar.style.animation = `ps-fill ${DELAY}ms linear forwards`;
                timer = setTimeout(() => show(i + 1, 1), DELAY);
            };

            root.querySelector('[data-next]').addEventListener('click', () => show(i + 1, 1));
            root.querySelector('[data-prev]').addEventListener('click', () => show(i - 1, -1));
            tabs.forEach((t, k) => t.addEventListener('click', () => show(k, k > i ? 1 : -1)));
            root.addEventListener('mouseenter', () => { held = true; clearTimeout(timer); bar.style.animation = 'none'; });
            root.addEventListener('mouseleave', () => { held = false; restart(); });
            root.addEventListener('focusin', () => { held = true; clearTimeout(timer); bar.style.animation = 'none'; });
            root.addEventListener('focusout', () => { held = false; restart(); });
            restart();
        })();
    </script>

    @vite(['resources/js/past-seasons-3d.js'])
</x-layouts.public>
