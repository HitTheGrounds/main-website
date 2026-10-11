@php
    $ticker = ['Champion', 'Orange Cap', 'Purple Cap', 'Super Striker', 'Green Dot Ball', 'Player of the Match'];
@endphp

<x-layouts.public>
    {{-- Hero --}}
    <section class="overflow-hidden bg-base-100">
        <x-PageContainer class="!py-10 sm:!py-12 lg:!py-14">
            <div class="flex flex-col items-center gap-6 lg:flex-row lg:gap-10">
                <h1 class="font-heading text-center text-[clamp(3rem,8vw,6.4rem)] leading-[.95] lg:flex-1" data-aos="fade-up">
                    <span class="text-base-content">Awards &amp; </span><span class="text-primary">Recognition</span>
                </h1>

                <div class="relative h-[340px] w-full [perspective:1200px] sm:h-[520px] lg:h-[620px] lg:flex-1" aria-hidden="true" data-aos="fade-up" data-aos-delay="120">
                    <div class="aw-glow absolute inset-0" style="background: radial-gradient(52% 52% at 50% 52%, rgb(255 106 19 / .2), transparent 72%)"></div>
                    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                        <div class="aw-spin size-[280px] rounded-full border-2 border-dashed border-primary/50 sm:size-[460px]"></div>
                    </div>
                    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                        <div class="aw-spin-r size-[200px] rounded-full border border-base-content/15 sm:size-[350px]"></div>
                    </div>
                    <div class="aw-glow absolute bottom-4 left-1/2 h-12 w-[300px] -translate-x-1/2 rounded-[50%]"
                         style="background: radial-gradient(closest-side, rgb(255 106 19 / .7), transparent)"></div>

                    <div class="aw-float absolute bottom-14 left-0 [animation-delay:-2s] [perspective:800px] sm:left-[2%]">
                        <img src="/award/award_2.avif" alt="" class="aw-sway aw-silver h-[105px] w-auto [animation-delay:-3s] sm:h-[210px] lg:h-[240px]">
                    </div>
                    <div class="aw-float absolute bottom-12 right-0 [animation-delay:-4s] [perspective:800px] sm:right-[3%]">
                        <img src="/award/award_3.avif" alt="" class="aw-sway aw-gold h-[120px] w-auto [animation-delay:-1s] sm:h-[240px] lg:h-[270px]">
                    </div>
                    <div class="aw-float absolute inset-x-0 bottom-0 z-10 flex justify-center [perspective:1000px]">
                        <img src="/award/award_1.avif" alt="" class="aw-sway aw-orange h-[290px] w-auto sm:h-[470px] lg:h-[550px]">
                    </div>

                    <span class="aw-spark" style="left: 12%; animation-delay: 0s"></span>
                    <span class="aw-spark" style="left: 26%; animation-delay: 1.4s; width: 6px; height: 6px"></span>
                    <span class="aw-spark" style="left: 41%; animation-delay: 3.1s"></span>
                    <span class="aw-spark" style="left: 58%; animation-delay: .7s; width: 7px; height: 7px"></span>
                    <span class="aw-spark" style="left: 72%; animation-delay: 2.2s"></span>
                    <span class="aw-spark" style="left: 86%; animation-delay: 4.3s; width: 6px; height: 6px"></span>
                </div>
            </div>
        </x-PageContainer>
    </section>

    {{-- Ticker --}}
    <div class="overflow-hidden whitespace-nowrap bg-primary py-4 text-secondary" aria-hidden="true">
        <div class="aw-marquee">
            @foreach ([1, 2] as $copy)
                <span class="inline-flex items-center gap-9 pr-9 text-xl font-extrabold uppercase tracking-wider">
                    @foreach ($ticker as $name)
                        <span>{{ $name }}</span><span class="opacity-45">&#9679;</span>
                    @endforeach
                </span>
            @endforeach
        </div>
    </div>

    {{-- Team honours --}}
    <section id="team" class="bg-base-100">
        <x-PageContainer class="!pb-6">
            <div class="mb-10 text-center" data-aos="fade-up">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">Team honours</p>
                <h2 class="mt-3 font-heading text-[clamp(2.2rem,5vw,3.6rem)] text-base-content">Who lifts the cup</h2>
            </div>

            <div class="grid gap-5 sm:gap-7 lg:grid-cols-2">
                <article class="aw-card rounded-[2rem] text-base-100 shadow-[0_30px_60px_-30px_rgb(31_27_22_/_.55)]" data-aos="fade-up"
                         style="background: radial-gradient(75% 42% at 50% 36%, rgb(255 138 61 / .5), transparent 75%), #17120E">
                    <div class="relative flex h-[270px] items-end justify-center [perspective:900px] sm:h-[400px]">
                        <div class="absolute bottom-[-170px] left-1/2 -ml-[230px]">
                            <div class="aw-spin size-[460px] rounded-full border-2 border-dashed border-orange-400/40"></div>
                        </div>
                        <span class="absolute left-5 top-4 text-[78px] font-extrabold sm:left-7 sm:top-5 sm:text-[110px] leading-none tracking-tighter text-transparent [-webkit-text-stroke:1.5px_rgb(244_241_234_/_.25)]" aria-hidden="true">01</span>
                        <img src="/award/award_1.avif" alt="Champion trophy" class="aw-tilt aw-float aw-gold relative h-[235px] w-auto sm:h-[360px]">
                    </div>
                    <div class="px-6 pb-7 pt-1 text-center sm:px-9 sm:pb-10 sm:pt-2">
                        <h3 class="font-heading text-3xl sm:text-4xl">Champion</h3>
                        <p class="mt-2 text-base leading-relaxed text-base-100/70 sm:mt-2.5 sm:text-lg">Awarded to the tournament-winning team.</p>
                    </div>
                </article>

                <article class="aw-card flex items-center rounded-[2rem] shadow-[0_30px_60px_-34px_rgb(31_27_22_/_.35)] sm:block" data-aos="fade-up" data-aos-delay="100"
                         style="background: radial-gradient(75% 42% at 50% 36%, rgb(255 106 19 / .22), transparent 75%), #EBE6DB">
                    <div class="relative flex h-[160px] w-[38%] shrink-0 items-center justify-center [perspective:900px] sm:h-[400px] sm:w-auto sm:shrink sm:items-end">
                        <span class="absolute left-7 top-5 hidden text-[110px] font-extrabold leading-none tracking-tighter text-transparent [-webkit-text-stroke:1.5px_rgb(31_27_22_/_.2)] sm:block" aria-hidden="true">02</span>
                        <img src="/award/award_2.avif" alt="Runners-up shield" class="aw-tilt aw-float aw-silver relative h-[125px] w-auto [animation-delay:-2s] sm:h-[340px]">
                    </div>
                    <div class="flex-1 px-4 py-5 text-center sm:px-9 sm:pb-10 sm:pt-2">
                        <h3 class="font-heading text-2xl text-base-content sm:text-4xl">1st Runners-up</h3>
                        <p class="mt-2 text-sm leading-relaxed text-base-content/70 sm:mt-2.5 sm:text-lg">Awarded to the tournament’s second-place team.</p>
                    </div>
                </article>
            </div>
        </x-PageContainer>
    </section>

    {{-- Player honours --}}
    <section class="bg-base-100">
        <x-PageContainer class="!pt-6">
            <div class="mb-10 text-center" data-aos="fade-up">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">Player honours</p>
                <h2 class="mt-3 font-heading text-[clamp(2.2rem,5vw,3.6rem)] text-base-content">Best of the season</h2>
            </div>

            <div class="grid gap-5 sm:gap-7 lg:grid-cols-2">
                @foreach ([
                    ['Male', 'Best overall male player of the season.', 'aw-orange', 'rgb(255 106 19 / .3)', ''],
                    ['Female', 'Best overall female player of the season.', 'aw-violet', 'rgb(139 92 246 / .28)', '[animation-delay:-3s]'],
                ] as [$gender, $text, $tint, $glow, $delay])
                    <article class="aw-card flex items-center rounded-[2rem] border border-base-300 bg-[#FBFAF6]" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                        <div class="flex h-[175px] w-[34%] shrink-0 items-end justify-center [perspective:800px] [mask-image:linear-gradient(90deg,#000_62%,transparent)] sm:h-[320px] sm:w-2/5 sm:[mask-image:none]"
                             style="background: radial-gradient(70% 70% at 50% 75%, {{ $glow }}, transparent 72%)">
                            <img src="/award/award_3.avif" alt="Most Valuable Player award" class="aw-tilt aw-float {{ $tint }} h-[150px] w-auto {{ $delay }} sm:h-[290px]">
                        </div>
                        <div class="w-full p-4 text-center sm:p-8">
                            <span class="inline-block rounded-full bg-secondary px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-secondary-content">MVP</span>
                            <h3 class="mt-2 font-heading text-xl leading-tight text-base-content sm:mt-4 sm:text-3xl">Most Valuable Player ({{ $gender }})</h3>
                            <p class="mt-2 text-sm leading-relaxed text-base-content/70 sm:mt-3 sm:text-lg">{{ $text }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </x-PageContainer>
    </section>

    {{-- The caps --}}
    <section class="grid bg-[#17120E] text-base-100 md:grid-cols-2">
        <article class="aw-card aw-still flex min-h-[330px] flex-col justify-between p-6 text-secondary sm:min-h-[400px] sm:p-9"
                 style="background: linear-gradient(155deg, #FF9248 0%, #F2600C 55%, #B8480A 100%)" data-aos="fade-up">
            <span class="pointer-events-none absolute right-3 top-4 text-[1.9rem] sm:-right-1.5 sm:bottom-20 sm:top-auto sm:text-[clamp(3rem,8vw,6.5rem)] font-extrabold leading-[.8] tracking-tighter text-transparent [-webkit-text-stroke:2px_rgb(31_27_22_/_.22)]" aria-hidden="true">RUNS</span>
            <div class="flex justify-center pt-3">
                <x-award-cap id="orange" label="Orange cap" class="h-auto w-[165px] max-w-full sm:w-[230px] drop-shadow-[0_26px_24px_rgb(31_27_22_/_.45)]"
                    :dome="['#FFC48F', '#FF6A13', '#A63E08']" :brim="['#E8590C', '#7E2D05']" band="#8A3306" ink="#1F1B16" />
            </div>
            <div class="relative text-center">
                <span class="inline-block rounded-full bg-secondary px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-[#FF9248]">Leading run-scorer</span>
                <h3 class="mt-4 font-heading text-[clamp(2.4rem,5vw,3.4rem)] leading-[.95]">Orange Cap</h3>
                <p class="mx-auto mt-2.5 max-w-md text-[17px] leading-relaxed max-sm:text-balance text-secondary/80">Awarded to the tournament’s leading run-scorer.</p>
            </div>
        </article>

        <article class="aw-card aw-still flex min-h-[330px] flex-col justify-between p-6 text-base-100 sm:min-h-[400px] sm:p-9"
                 style="background: linear-gradient(155deg, #9D78F5 0%, #6B2FD0 52%, #3A1380 100%)" data-aos="fade-up" data-aos-delay="100">
            <span class="pointer-events-none absolute right-3 top-4 text-[1.9rem] sm:-right-1.5 sm:bottom-20 sm:top-auto sm:text-[clamp(3rem,8vw,6.5rem)] font-extrabold leading-[.8] tracking-tighter text-transparent [-webkit-text-stroke:2px_rgb(244_241_234_/_.2)]" aria-hidden="true">WICKETS</span>
            <div class="flex justify-center pt-3">
                <x-award-cap id="purple" label="Purple cap" class="h-auto w-[165px] max-w-full sm:w-[230px] drop-shadow-[0_26px_24px_rgb(20_8_50_/_.55)]"
                    :dome="['#D3B8FF', '#8B5CF6', '#3A1380']" :brim="['#6B2FD0', '#2A0C63']" band="#2A0C63" ink="#F4F1EA" seam="rgba(20,8,50,.28)" />
            </div>
            <div class="relative text-center">
                <span class="inline-block rounded-full bg-base-100 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-[#4B1DA0]">Leading wicket-taker</span>
                <h3 class="mt-4 font-heading text-[clamp(2.4rem,5vw,3.4rem)] leading-[.95]">Purple Cap</h3>
                <p class="mx-auto mt-2.5 max-w-md text-[17px] leading-relaxed max-sm:text-balance text-base-100/85">Awarded to the tournament’s leading wicket-taker.</p>
            </div>
        </article>
    </section>

    {{-- Moments and skills --}}
    <section class="bg-base-100">
        <x-PageContainer class="!pb-6">
            <div class="mb-10 text-center" data-aos="fade-up">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">Moments and skills</p>
                <h2 class="mt-3 font-heading text-[clamp(2.2rem,5vw,3.6rem)] text-base-content">Every ball counts</h2>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:gap-6 lg:grid-cols-4">
                <article class="aw-card flex min-h-[250px] sm:min-h-[340px] flex-col items-center justify-between text-center rounded-[1.75rem] border border-base-300 bg-[#FBFAF6] p-4 sm:p-7" data-aos="fade-up">
                    <div class="text-[92px] font-extrabold leading-[.85] sm:text-[160px] tracking-tighter text-primary" aria-hidden="true">6</div>
                    <div>
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl text-base-content">Super Striker of the Tournament</h3>
                        <p class="mt-2 text-[13px] leading-snug sm:mt-2.5 sm:text-base sm:leading-relaxed text-base-content/70">Awarded to the player with the most sixes hit.</p>
                    </div>
                </article>

                <article class="aw-card flex min-h-[250px] sm:min-h-[340px] flex-col items-center justify-between text-center rounded-[1.75rem] bg-secondary p-4 sm:p-7 text-secondary-content" data-aos="fade-up" data-aos-delay="80">
                    <div class="text-[92px] font-extrabold leading-[.85] sm:text-[160px] tracking-tighter text-transparent [-webkit-text-stroke:3px_var(--color-primary)]" aria-hidden="true">4</div>
                    <div>
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl">4s of the Tournament</h3>
                        <p class="mt-2 text-[13px] leading-snug sm:mt-2.5 sm:text-base sm:leading-relaxed text-secondary-content/72">Awarded to the player with the most fours hit.</p>
                    </div>
                </article>

                <article class="aw-card flex min-h-[250px] sm:min-h-[340px] flex-col items-center justify-between text-center rounded-[1.75rem] border border-[#BFD8C6] bg-[#E3EFE6] p-4 sm:p-7" data-aos="fade-up" data-aos-delay="160">
                    <div class="relative mt-1 size-[80px] sm:mt-4 sm:size-[120px]" aria-hidden="true">
                        <span class="aw-pulse"></span>
                        <span class="aw-pulse [animation-delay:1.3s]"></span>
                        <span class="absolute inset-[22px] rounded-full shadow-[0_14px_24px_-8px_rgb(30_75_46_/_.6)]"
                              style="background: radial-gradient(circle at 32% 28%, #5FC184, #2E7D4F 60%, #1B4B2E)"></span>
                    </div>
                    <div>
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl text-base-content">Green Dot Ball Award</h3>
                        <p class="mt-2 text-[13px] leading-snug sm:mt-2.5 sm:text-base sm:leading-relaxed text-base-content/72">Awarded to the bowler with the most dot balls delivered.</p>
                    </div>
                </article>

                <article class="aw-card flex min-h-[250px] sm:min-h-[340px] flex-col items-center justify-between text-center rounded-[1.75rem] p-4 sm:p-7 text-base-100 shadow-[0_30px_50px_-28px_rgb(232_163_23_/_.55)]"
                         style="background: linear-gradient(160deg, #3A2A14 0%, #1F1710 55%, #120D08 100%)" data-aos="fade-up" data-aos-delay="240">
                    <div class="aw-glow pointer-events-none absolute left-1/2 top-4 size-[170px] -translate-x-1/2 sm:top-7 sm:size-[260px] rounded-full" aria-hidden="true"
                         style="background: radial-gradient(closest-side, rgb(255 196 64 / .6), rgb(255 106 19 / .18) 65%, transparent)"></div>
                    <div class="pointer-events-none absolute left-1/2 top-[52px] hidden -translate-x-1/2 sm:block" aria-hidden="true">
                        <div class="aw-spin size-[210px] rounded-full border-2 border-dashed border-amber-300/55"></div>
                    </div>
                    <span class="pointer-events-none absolute right-4 top-3 hidden text-5xl sm:block font-extrabold leading-none tracking-tight text-transparent [-webkit-text-stroke:1.5px_rgb(255_206_92_/_.4)]" aria-hidden="true">FINAL</span>
                    <div class="relative mt-3 flex h-[105px] items-end sm:mt-8 sm:h-[150px] justify-center [perspective:700px]" aria-hidden="true">
                        <img src="/award/award_2.avif" alt="" class="aw-tilt aw-final h-[105px] w-auto sm:h-[150px]">
                    </div>
                    <div class="relative text-center">
                        <span class="inline-block rounded-full bg-[#FFC440] px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-[0.14em] text-secondary">Match winner</span>
                        <h3 class="mt-3 font-heading text-[17px] leading-tight sm:text-2xl">Player of the Match: Final</h3>
                        <p class="mt-2 text-[13px] leading-snug sm:mt-2.5 sm:text-base sm:leading-relaxed text-base-100/78">Awarded to the standout performer in the final.</p>
                    </div>
                </article>
            </div>
        </x-PageContainer>
    </section>

    {{-- Call to action --}}
    <section class="bg-base-100">
        <x-PageContainer class="!pt-10">
            <div class="relative flex flex-col items-center justify-center gap-7 text-center overflow-hidden rounded-[2.25rem] bg-[#17120E] px-7 py-12 text-base-100 sm:px-12" data-aos="fade-up">
                <div class="aw-glow pointer-events-none absolute -right-20 -top-28 size-[480px] rounded-full" aria-hidden="true"
                     style="background: radial-gradient(closest-side, rgb(255 106 19 / .55), transparent)"></div>
                <h2 class="relative font-heading text-[clamp(2.2rem,5vw,3.6rem)]">Ready to compete?</h2>
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
</x-layouts.public>
