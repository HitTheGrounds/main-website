@php
    $ticker = ['Champion', 'First Runner-up', 'Male MVP', 'Female MVP', 'Orange Cap', 'Purple Cap', 'Super Striker', 'Most Four Hits', 'Green Dot Ball', 'Player of the Match'];
@endphp

<x-layouts.public title="Awards" description="Every award at Hit the Grounds 2026: the Champion and First Runner-up awards, Male and Female MVP, Orange Cap, Purple Cap, Super Striker, Most Four Hits, Green Dot Ball and Player of the Match.">
    {{-- Hero --}}
    <section class="overflow-hidden bg-base-100">
        <x-PageContainer class="!py-10 sm:!py-12 lg:!py-14">
            <div class="flex flex-col items-center gap-6 lg:flex-row lg:gap-10">
                <h1 class="font-heading text-center text-[clamp(3rem,8vw,6.4rem)] leading-[.95] lg:flex-1" data-aos="fade-up">
                    <span class="text-base-content">Awards &amp; </span><span class="text-primary">Recognition</span>
                </h1>

                {{-- 3D podium (resources/js/awards-podium.js). The 2D trophies below are the fallback when WebGL is off. --}}
                <div id="awards-podium" role="img" aria-label="A rotating 3D podium with the champion, runners-up and MVP trophies"
                     class="group relative h-[400px] w-full overflow-hidden rounded-[2rem] shadow-[0_30px_60px_-30px_rgb(31_27_22_/_.6)] sm:h-[520px] lg:h-[620px] lg:flex-1"
                     style="background: radial-gradient(70% 60% at 50% 38%, #3a2113 0%, #14100d 62%, #0b0907 100%)" data-aos="fade-up" data-aos-delay="120">
                    <div data-fallback class="absolute inset-0 transition-opacity duration-700" aria-hidden="true">
                        <div class="aw-float absolute bottom-10 left-[4%] sm:bottom-14"><img src="/award/award_silver.avif" alt="" class="h-[110px] w-auto sm:h-[220px]"></div>
                        <div class="aw-float absolute bottom-10 right-[4%] sm:bottom-14"><img src="/award/mvp_male.avif" alt="" width="437" height="848" class="h-[130px] w-auto sm:h-[250px]"></div>
                        <div class="aw-float absolute inset-x-0 bottom-6 flex justify-center"><img src="/award/award_gold.avif" alt="" class="h-[250px] w-auto sm:h-[430px]"></div>
                    </div>
                    <p class="pointer-events-none absolute bottom-3 left-0 right-0 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-base-100/45 opacity-0 transition-opacity duration-500 group-data-[ready]:opacity-100" aria-hidden="true">Drag to spin</p>
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
                        <img src="/award/award_1.avif" alt="Champion Award trophy" class="aw-tilt aw-float aw-gold relative h-[235px] w-auto sm:h-[360px]">
                    </div>
                    <div class="px-6 pb-7 pt-1 text-center sm:px-9 sm:pb-10 sm:pt-2">
                        <h3 class="font-heading text-3xl sm:text-4xl">Champion Award</h3>
                        <p class="mt-2 text-base leading-relaxed text-base-100/70 sm:mt-2.5 sm:text-lg">Awarded to the tournament-winning team.</p>
                    </div>
                </article>

                <article class="aw-card flex items-center rounded-[2rem] shadow-[0_30px_60px_-34px_rgb(31_27_22_/_.35)] sm:block" data-aos="fade-up" data-aos-delay="100"
                         style="background: radial-gradient(75% 42% at 50% 36%, rgb(255 106 19 / .22), transparent 75%), #EBE6DB">
                    <div class="relative flex h-[160px] w-[38%] shrink-0 items-center justify-center [perspective:900px] sm:h-[400px] sm:w-auto sm:shrink sm:items-end">
                        <span class="absolute left-7 top-5 hidden text-[110px] font-extrabold leading-none tracking-tighter text-transparent [-webkit-text-stroke:1.5px_rgb(31_27_22_/_.2)] sm:block" aria-hidden="true">02</span>
                        <img src="/award/award_silver.avif" alt="First Runner-up Award trophy" class="aw-tilt aw-float relative h-[125px] w-auto drop-shadow-[0_24px_24px_rgb(31_27_22_/_.4)] [animation-delay:-2s] sm:h-[340px]">
                    </div>
                    <div class="flex-1 px-4 py-5 text-center sm:px-9 sm:pb-10 sm:pt-2">
                        <h3 class="font-heading text-2xl text-base-content sm:text-4xl">First Runner-up Award</h3>
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
                    ['Male', 'Best overall male player of the season.', 'male', 'rgb(255 106 19 / .28)', ''],
                    ['Female', 'Best overall female player of the season.', 'female', 'rgb(139 92 246 / .28)', '[animation-delay:-3s]'],
                ] as [$gender, $text, $file, $glow, $delay])
                    <article class="aw-card flex items-center rounded-[2rem] border border-base-300 bg-[#FBFAF6]" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                        <div class="flex h-[200px] w-[34%] shrink-0 items-end justify-center [perspective:800px] sm:h-[340px] sm:w-2/5"
                             style="background: radial-gradient(70% 70% at 50% 62%, {{ $glow }}, transparent 72%)">
                            <img src="/award/mvp_{{ $file }}.avif" alt="{{ $gender }} MVP trophy" width="437" height="848" loading="lazy"
                                 class="aw-tilt aw-float mb-3 h-[170px] w-auto drop-shadow-[0_20px_22px_rgb(31_27_22_/_.4)] {{ $delay }} sm:mb-5 sm:h-[300px]">
                        </div>
                        <div class="w-full p-4 text-center sm:p-8">
                            <span class="inline-block rounded-full bg-secondary px-3 py-1.5 text-xs font-bold uppercase tracking-[0.14em] text-secondary-content">MVP</span>
                            <h3 class="mt-2 font-heading text-xl leading-tight text-base-content sm:mt-4 sm:text-3xl">{{ $gender }} MVP Award</h3>
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
                <h3 class="mt-4 font-heading text-[clamp(2.4rem,5vw,3.4rem)] leading-[.95]">Orange Cap Award</h3>
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
                <h3 class="mt-4 font-heading text-[clamp(2.4rem,5vw,3.4rem)] leading-[.95]">Purple Cap Award</h3>
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
                    <div class="relative flex h-[130px] w-full items-end justify-center sm:h-[210px]">
                        <img src="/award/super_striker.avif" alt="Super Striker trophy" width="675" height="1299" loading="lazy" class="aw-tilt aw-float relative h-full w-auto drop-shadow-[0_18px_18px_rgb(31_27_22_/_.4)]">
                    </div>
                    <div>
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl text-base-content">Super Striker Award</h3>
                        <p class="mt-2 text-[13px] leading-snug sm:mt-2.5 sm:text-base sm:leading-relaxed text-base-content/70">Awarded to the player with the most sixes hit.</p>
                    </div>
                </article>

                <article class="aw-card flex min-h-[250px] sm:min-h-[340px] flex-col items-center justify-between text-center rounded-[1.75rem] bg-secondary p-4 sm:p-7 text-secondary-content" data-aos="fade-up" data-aos-delay="80">
                    <div class="relative flex h-[130px] w-full items-end justify-center sm:h-[210px]">
                        <img src="/award/most_fours.avif" alt="Most Four Hits trophy" width="675" height="1299" loading="lazy" class="aw-tilt aw-float relative h-full w-auto [animation-delay:-2s] drop-shadow-[0_18px_18px_rgb(0_0_0_/_.55)]">
                    </div>
                    <div>
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl">Most Four Hits Award</h3>
                        <p class="mt-2 text-[13px] leading-snug sm:mt-2.5 sm:text-base sm:leading-relaxed text-secondary-content/72">Awarded to the player with the most fours hit.</p>
                    </div>
                </article>

                <article class="aw-card flex min-h-[250px] sm:min-h-[340px] flex-col items-center justify-between text-center rounded-[1.75rem] border border-[#BFD8C6] bg-[#E3EFE6] p-4 sm:p-7" data-aos="fade-up" data-aos-delay="160">
                    <div class="relative flex h-[130px] w-full items-end justify-center sm:h-[210px]">
                        <img src="/award/green_dot.avif" alt="Green Dot Ball trophy" width="675" height="1299" loading="lazy" class="aw-tilt aw-float relative h-full w-auto [animation-delay:-4s] drop-shadow-[0_18px_18px_rgb(30_75_46_/_.45)]">
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
                    <div class="relative mt-3 flex h-[105px] items-end sm:mt-8 sm:h-[150px] justify-center [perspective:700px]" aria-hidden="true">
                        <img src="/award/award_2.avif" alt="" class="aw-tilt aw-final h-[105px] w-auto sm:h-[150px]">
                    </div>
                    <div class="relative text-center">
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl">Player of the Match Award</h3>
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

    @vite(['resources/js/awards-podium.js'])
</x-layouts.public>
