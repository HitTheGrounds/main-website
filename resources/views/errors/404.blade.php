<x-layouts.public title="Page not found" description="This page is out. Head back to the Hit the Grounds 2026 home page.">
    <style>
        .nf-ball { animation: nf-roll 6s linear infinite; }
        .nf-spin { transform-box: fill-box; transform-origin: center; animation: nf-spin .55s linear infinite; }
        .nf-stumps { transform-box: fill-box; transform-origin: 50% 100%; animation: nf-wobble 6s ease-out infinite; }
        .nf-bail-l { animation: nf-bail-l 6s ease-out infinite; }
        .nf-bail-r { animation: nf-bail-r 6s ease-out infinite; }
        .nf-zero { animation: nf-bob 4.5s ease-in-out infinite; }
        .nf-rise { opacity: 0; animation: nf-rise .8s var(--ease-out-expo, cubic-bezier(.16, 1, .3, 1)) forwards; }

        @keyframes nf-roll {
            0% { transform: translateX(0); opacity: 1; }
            38% { transform: translateX(440px); opacity: 1; }
            60% { transform: translateX(530px); opacity: 1; }
            72% { transform: translateX(600px); opacity: 0; }
            96% { transform: translateX(0); opacity: 0; }
            100% { transform: translateX(0); opacity: 1; }
        }
        @keyframes nf-spin { to { transform: rotate(360deg); } }
        @keyframes nf-wobble {
            0%, 37% { transform: rotate(0); }
            40% { transform: rotate(2.4deg); }
            46% { transform: rotate(-1.6deg); }
            54% { transform: rotate(.8deg); }
            62%, 100% { transform: rotate(0); }
        }
        @keyframes nf-bail-l {
            0%, 37% { transform: translate(0, 0) rotate(0); opacity: 1; }
            52% { transform: translate(-34px, -58px) rotate(-210deg); opacity: 1; }
            72%, 94% { transform: translate(-66px, 98px) rotate(-420deg); opacity: 1; }
            97% { opacity: 0; }
            100% { transform: translate(0, 0) rotate(0); opacity: 1; }
        }
        @keyframes nf-bail-r {
            0%, 37% { transform: translate(0, 0) rotate(0); opacity: 1; }
            52% { transform: translate(30px, -74px) rotate(250deg); opacity: 1; }
            72%, 94% { transform: translate(58px, 98px) rotate(470deg); opacity: 1; }
            97% { opacity: 0; }
            100% { transform: translate(0, 0) rotate(0); opacity: 1; }
        }
        @keyframes nf-bob { 0%, 100% { transform: translateY(0) rotate(-3deg); } 50% { transform: translateY(-14px) rotate(3deg); } }
        @keyframes nf-rise { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: none; } }

        @media (prefers-reduced-motion: reduce) {
            .nf-ball, .nf-spin, .nf-stumps, .nf-bail-l, .nf-bail-r, .nf-zero { animation: none; }
            .nf-ball { transform: translateX(300px); }
            .nf-rise { animation: none; opacity: 1; }
        }
    </style>

    <section class="relative overflow-hidden bg-base-100">
        <x-PageContainer class="!py-10 sm:!py-14 lg:!py-16">
            <div class="mx-auto flex max-w-3xl flex-col items-center text-center">
                <p class="nf-rise text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">Error 404</p>

                <h1 class="nf-rise mt-2 flex items-center justify-center font-heading text-[clamp(7rem,26vw,15rem)] leading-[.9]" style="animation-delay: .08s" aria-label="404">
                    <span class="text-base-content" aria-hidden="true">4</span>
                    <span class="nf-zero mx-[.02em] inline-block text-primary" aria-hidden="true">0</span>
                    <span class="text-base-content" aria-hidden="true">4</span>
                </h1>

                {{-- Bowled: the ball rolls in, hits the stumps and the bails fly --}}
                <svg viewBox="0 0 640 200" class="nf-rise mt-2 h-auto w-full max-w-2xl" style="animation-delay: .2s" role="img" aria-label="A cricket ball knocks the bails off the stumps">
                    <line x1="0" y1="172" x2="640" y2="172" stroke="#1F1B16" stroke-width="3" stroke-linecap="round" stroke-dasharray="2 14" opacity=".35"/>
                    <line x1="24" y1="172" x2="616" y2="172" stroke="#1F1B16" stroke-width="3" stroke-linecap="round" opacity=".12"/>

                    <g class="nf-stumps">
                        <rect x="509" y="76" width="9" height="96" rx="4.5" fill="#1F1B16"/>
                        <rect x="535" y="76" width="9" height="96" rx="4.5" fill="#1F1B16"/>
                        <rect x="561" y="76" width="9" height="96" rx="4.5" fill="#1F1B16"/>
                        <rect x="509" y="76" width="9" height="12" rx="4.5" fill="#FF6A13"/>
                        <rect x="535" y="76" width="9" height="12" rx="4.5" fill="#FF6A13"/>
                        <rect x="561" y="76" width="9" height="12" rx="4.5" fill="#FF6A13"/>
                    </g>
                    <g class="nf-bail-l" style="transform-box: fill-box; transform-origin: center"><rect x="511" y="68" width="30" height="6" rx="3" fill="#FF6A13"/></g>
                    <g class="nf-bail-r" style="transform-box: fill-box; transform-origin: center"><rect x="538" y="68" width="30" height="6" rx="3" fill="#B8480A"/></g>

                    <g class="nf-ball">
                        <ellipse cx="60" cy="173" rx="15" ry="3.5" fill="#1F1B16" opacity=".22"/>
                        <g class="nf-spin">
                            <circle cx="60" cy="156" r="15" fill="#FF6A13"/>
                            <circle cx="60" cy="156" r="15" fill="none" stroke="#B8480A" stroke-width="1.5"/>
                            <path d="M47 150c6 3 6 9 0 12M73 150c-6 3-6 9 0 12" fill="none" stroke="#F4F1EA" stroke-width="1.6" stroke-linecap="round" stroke-dasharray="2 3"/>
                        </g>
                    </g>
                </svg>

                <h2 class="nf-rise mt-4 font-heading text-[clamp(2rem,5vw,3.2rem)] text-base-content" style="animation-delay: .3s">Bowled! That page is out.</h2>
                <p class="nf-rise mx-auto mt-3 max-w-xl text-lg leading-relaxed text-base-content/70" style="animation-delay: .38s">
                    The link you followed missed the stumps. The page may have moved or never existed, so let us get you back in the game.
                </p>

                <div class="nf-rise mt-8 flex flex-col items-center gap-3 sm:flex-row" style="animation-delay: .46s">
                    <a href="{{ route('home') }}"
                       class="inline-flex items-center gap-3.5 rounded-full bg-primary py-2 pl-7 pr-2 text-[17px] font-bold text-secondary transition hover:-translate-y-0.5 hover:bg-[#ff7a2b]">
                        Back to home
                        <span class="grid size-11 place-items-center rounded-full bg-secondary text-secondary-content">
                            <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </span>
                    </a>
                    <a href="{{ route('timeline') }}" class="inline-flex items-center rounded-full border-2 border-base-content/20 px-7 py-3 text-[17px] font-bold text-base-content transition hover:-translate-y-0.5 hover:border-primary hover:text-primary-ink">
                        View timeline
                    </a>
                </div>
            </div>
        </x-PageContainer>
    </section>
</x-layouts.public>
