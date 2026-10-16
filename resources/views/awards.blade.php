@php
    $ticker = ['Champion', '1st Runners-up', 'Most Valuable Player (Male)', 'Most Valuable Player (Female)', 'Orange Cap', 'Purple Cap', 'Super Striker Award', '4s of the Tournament', 'The Dot Ball Dominator', 'Player of the Match'];
@endphp

<x-layouts.public title="Awards" description="Every award at Hit the Grounds 2026: the Champion, 1st Runners-up and Most Valuable Player (Male/Female), Orange Cap, Purple Cap, Super Striker Award, 4s of the Tournament, The Dot Ball Dominator and Player of the Match.">
    {{-- Hero --}}
    <section class="overflow-hidden bg-base-100">
        <x-PageContainer class="!py-10 sm:!py-12 lg:!py-14">
            <div class="flex flex-col items-center gap-6 lg:flex-row lg:gap-10">
                <h1 class="font-heading text-center text-[clamp(3rem,8vw,6.4rem)] leading-[.95] lg:flex-1" data-aos="fade-up">
                    <span class="text-base-content">Awards &amp; </span><span class="text-primary">Recognition</span>
                </h1>

                <x-AwardsStage />
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
                        <span class="absolute left-5 top-4 text-[78px] font-extrabold sm:left-7 sm:top-5 sm:text-[110px] leading-none tracking-tighter text-transparent [-webkit-text-stroke:1.5px_rgb(244_241_234_/_.25)]" aria-hidden="true">01</span>
                        <img src="/award/champion.avif" alt="Champion trophy" class="aw-tilt aw-float aw-gold relative h-[235px] w-auto sm:h-[360px]">
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
                        <img src="/award/1st-runners-up.avif" alt="1st Runners-up trophy" class="aw-tilt aw-float relative h-[125px] w-auto max-w-full object-contain drop-shadow-[0_24px_24px_rgb(31_27_22_/_.4)] [animation-delay:-2s] sm:h-[340px]">
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
                    ['Male', 'Best overall male player of the season.', 'male', 'rgb(255 106 19 / .28)', ''],
                    ['Female', 'Best overall female player of the season.', 'female', 'rgb(139 92 246 / .28)', '[animation-delay:-3s]'],
                ] as [$gender, $text, $file, $glow, $delay])
                    <article class="aw-card flex items-center rounded-[2rem] border border-base-300 bg-[#FBFAF6]" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                        <div class="flex h-[200px] w-[34%] shrink-0 items-end justify-center [perspective:800px] sm:h-[340px] sm:w-2/5"
                             style="background: radial-gradient(70% 70% at 50% 62%, {{ $glow }}, transparent 72%)">
                            <img src="/award/mvp_{{ $file }}.avif" alt="Most Valuable Player ({{ $gender }}) trophy" width="1122" height="1402" loading="lazy"
                                 class="aw-tilt aw-float mb-3 h-[170px] w-auto max-w-full object-contain drop-shadow-[0_20px_22px_rgb(31_27_22_/_.4)] {{ $delay }} sm:mb-5 sm:h-[300px]">
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
                <img src="/award/orange_cap.avif" alt="Orange cap with the Hit the Grounds 2026 logo" width="1254" height="1254" loading="lazy"
                     class="h-[125px] w-[165px] max-w-full object-contain sm:h-[174px] sm:w-[230px] drop-shadow-[0_26px_24px_rgb(31_27_22_/_.35)]" />
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
                <img src="/award/purple_cap.avif" alt="Purple cap with the Hit the Grounds 2026 logo" width="1254" height="1254" loading="lazy"
                     class="h-[125px] w-[165px] max-w-full object-contain sm:h-[174px] sm:w-[230px] drop-shadow-[0_26px_24px_rgb(20_8_50_/_.45)]" />
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
                    <div class="relative flex h-[130px] w-full items-end justify-center sm:h-[210px]">
                        <img src="/award/super_striker.avif" alt="Super Striker recognition medal" width="1254" height="1254" loading="lazy" class="aw-tilt aw-float relative h-full w-auto max-w-full object-contain drop-shadow-[0_18px_18px_rgb(31_27_22_/_.4)]">
                    </div>
                    <div>
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl text-base-content">Super Striker Award</h3>
                        <p class="mt-2 text-[13px] leading-snug sm:mt-2.5 sm:text-base sm:leading-relaxed text-base-content/70">Awarded to the player with the most sixes hit.</p>
                    </div>
                </article>

                <article class="aw-card flex min-h-[250px] sm:min-h-[340px] flex-col items-center justify-between text-center rounded-[1.75rem] bg-secondary p-4 sm:p-7 text-secondary-content" data-aos="fade-up" data-aos-delay="80">
                    <div class="relative flex h-[130px] w-full items-end justify-center sm:h-[210px]">
                        <img src="/award/most_fours.avif" alt="4s of the Tournament recognition medal" width="1254" height="1254" loading="lazy" class="aw-tilt aw-float relative h-full w-auto max-w-full object-contain drop-shadow-[0_18px_18px_rgb(0_0_0_/_.55)]">
                    </div>
                    <div>
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl">4s of the Tournament</h3>
                        <p class="mt-2 text-[13px] leading-snug sm:mt-2.5 sm:text-base sm:leading-relaxed text-secondary-content/72">Awarded to the player with the most fours hit.</p>
                    </div>
                </article>

                <article class="aw-card flex min-h-[250px] sm:min-h-[340px] flex-col items-center justify-between text-center rounded-[1.75rem] border border-base-300 bg-[#EBE6DB] p-4 sm:p-7" data-aos="fade-up" data-aos-delay="160">
                    <div class="relative flex h-[130px] w-full items-end justify-center sm:h-[210px]">
                        <img src="/award/dot_ball.avif" alt="The Dot Ball Dominator recognition medal" width="1254" height="1254" loading="lazy" class="aw-tilt aw-float relative h-full w-auto max-w-full object-contain drop-shadow-[0_18px_18px_rgb(31_27_22_/_.35)]">
                    </div>
                    <div>
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl text-base-content">The Dot Ball Dominator</h3>
                        <p class="mt-2 text-[13px] leading-snug sm:mt-2.5 sm:text-base sm:leading-relaxed text-base-content/72">Awarded to the bowler with the most dot balls delivered.</p>
                    </div>
                </article>

                <article class="aw-card flex min-h-[250px] sm:min-h-[340px] flex-col items-center justify-between text-center rounded-[1.75rem] p-4 sm:p-7 text-base-100 shadow-[0_30px_50px_-28px_rgb(232_163_23_/_.55)]"
                         style="background: linear-gradient(160deg, #3A2A14 0%, #1F1710 55%, #120D08 100%)" data-aos="fade-up" data-aos-delay="240">
                    <div class="aw-glow pointer-events-none absolute left-1/2 top-4 size-[170px] -translate-x-1/2 sm:top-7 sm:size-[260px] rounded-full" aria-hidden="true"
                         style="background: radial-gradient(closest-side, rgb(255 196 64 / .6), rgb(255 106 19 / .18) 65%, transparent)"></div>
                    <div class="relative flex h-[130px] w-full items-end justify-center sm:h-[210px]" aria-hidden="true">
                        <img src="/award/player_match.avif" alt="" width="1254" height="1254" loading="lazy" class="aw-tilt aw-float aw-final relative h-full w-auto max-w-full object-contain">
                    </div>
                    <div class="relative text-center">
                        <h3 class="font-heading text-[17px] leading-tight sm:text-2xl">Player of the Match</h3>
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
