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
                        <div class="aw-float absolute bottom-10 left-[4%] sm:bottom-14"><img src="/award/stage/1st-runners-up.avif" alt="" class="h-[150px] w-auto sm:h-[300px]"></div>
                        <div class="aw-float absolute bottom-10 right-[4%] sm:bottom-14"><img src="/award/stage/most-valuble-player-men.avif" alt="" class="h-[130px] w-auto sm:h-[260px]"></div>
                        <div class="aw-float absolute inset-x-0 bottom-6 flex justify-center"><img src="/award/stage/champions.avif" alt="" class="h-[260px] w-auto sm:h-[430px]"></div>
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

    @php
        $groups = [
            ['Team honours', 'Who lifts the cup', 'md:grid-cols-4', [
                ['champions', 'Champion Award', 'Awarded to the tournament-winning team.'],
                ['1st-runners-up', 'First Runner-up Award', 'Awarded to the tournament’s second-place team.'],
            ]],
            ['Player honours', 'Best of the season', 'md:grid-cols-4', [
                ['most-valuble-player-men', 'Male MVP Award', 'Best overall male player of the season.'],
                ['most-valuble-player-women', 'Female MVP Award', 'Best overall female player of the season.'],
            ]],
            ['The caps', 'Runs and wickets', 'md:grid-cols-4', [
                ['orange-cap', 'Orange Cap Award', 'Awarded to the tournament’s leading run-scorer.'],
                ['purple-cap', 'Purple Cap Award', 'Awarded to the tournament’s leading wicket-taker.'],
            ]],
            ['Moments and skills', 'Every ball counts', 'md:grid-cols-4', [
                ['super-striker-of-the-tournament', 'Super Striker Award', 'Awarded to the player with the most sixes hit.'],
                ['4s-of-the-tournament', 'Most Four Hits Award', 'Awarded to the player with the most fours hit.'],
                ['green-dot-ball', 'Green Dot Ball Award', 'Awarded to the bowler with the most dot balls delivered.'],
                ['player-of-the-match-final', 'Player of the Match Award', 'Awarded to the standout performer in the final.'],
            ]],
        ];
    @endphp

    @foreach ($groups as [$eyebrow, $title, , $items])
        <section class="bg-base-100">
            <x-PageContainer class="{{ $loop->first ? '!pb-6' : '!py-6' }}">
                <div class="mb-10 text-center" data-aos="fade-up">
                    <p class="text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">{{ $eyebrow }}</p>
                    <h2 class="mt-3 font-heading text-[clamp(2.2rem,5vw,3.6rem)] text-base-content">{{ $title }}</h2>
                </div>
                <div class="mx-auto flex max-w-5xl flex-wrap justify-center gap-3 sm:gap-6">
                    @foreach ($items as [$file, $name, $text])
                        <article class="aw-card w-[calc(50%-0.375rem)] overflow-hidden md:w-[calc(25%-1.125rem)] rounded-[1.25rem] border border-base-300 bg-[#17120E] shadow-[0_30px_60px_-34px_rgb(31_27_22_/_.5)]" data-aos="fade-up" data-aos-delay="{{ $loop->index * 80 }}">
                            <img src="/award/{{ $file }}.avif" alt="{{ $name }}" width="1000" loading="lazy" class="block h-auto w-full">
                            <div class="px-3 py-3 text-center text-base-100 sm:px-4 sm:py-4">
                                <h3 class="font-heading text-base leading-tight sm:text-lg">{{ $name }}</h3>
                                <p class="mt-1.5 text-xs leading-snug text-base-100/70 sm:text-sm">{{ $text }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </x-PageContainer>
        </section>
    @endforeach

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
