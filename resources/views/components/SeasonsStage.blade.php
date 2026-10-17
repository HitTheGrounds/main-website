@php
    $logos = config('seasons.logos');
    $seasons = config('seasons.results');

    $scene = collect($seasons)->map(function ($pair, $year) use ($logos) {
        [$champion, $runner] = $pair;
        return [
            'year' => $year,
            'champion' => $champion, 'championLogo' => $logos[$champion][0], 'championDark' => $logos[$champion][1] ?? false,
            'runner' => $runner, 'runnerLogo' => $logos[$runner][0], 'runnerDark' => $logos[$runner][1] ?? false,
        ];
    })->values();
@endphp

    {{-- Rotating podium --}}
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
