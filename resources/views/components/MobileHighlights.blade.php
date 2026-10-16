@php
    // [title, blurb, image]
    $awards = [
        ['Champion', 'The tournament-winning team.', 'champion'],
        ['1st Runners-up', 'The second-place team.', '1st-runners-up'],
        ['MVP (Male)', 'Best overall male player.', 'mvp_male'],
        ['MVP (Female)', 'Best overall female player.', 'mvp_female'],
        ['Orange Cap', 'Leading run-scorer.', 'orange_cap'],
        ['Purple Cap', 'Leading wicket-taker.', 'purple_cap'],
        ['Super Striker', 'Most sixes hit.', 'super_striker'],
        ['4s of the Tournament', 'Most fours hit.', 'most_fours'],
        ['Dot Ball Dominator', 'Most dot balls bowled.', 'dot_ball'],
        ['Player of the Match', 'Standout performer in the final.', 'player_match'],
    ];
@endphp

{{-- Mobile only: on larger screens these live on their own pages. --}}
<div class="lg:hidden" data-mobile-only>
    <section id="awards" class="bg-base-100">
        <x-PageContainer class="!pb-6">
            <div class="mb-6 text-center" data-aos="fade-up">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">Awards</p>
                <h2 class="mt-2 font-heading text-[clamp(2.2rem,9vw,3rem)] text-base-content">Awards &amp; <span class="text-primary">Recognition</span></h2>
            </div>
            <div class="mb-5"><x-AwardsStage /></div>
            <ul class="grid grid-cols-2 gap-3">
                @foreach ($awards as [$title, $blurb, $img])
                    <li class="rounded-[1.75rem] bg-[#17120E] p-3 text-center text-base-100">
                        <img src="/award/{{ $img }}.avif" alt="{{ $title }} trophy" loading="lazy" class="mx-auto h-28 w-auto max-w-full object-contain drop-shadow-[0_16px_16px_rgb(0_0_0_/_.5)]">
                        <h3 class="mt-3 font-heading text-lg leading-tight">{{ $title }}</h3>
                        <p class="mt-1 text-[13px] leading-snug text-base-100/70">{{ $blurb }}</p>
                    </li>
                @endforeach
            </ul>
        </x-PageContainer>
    </section>

    <section id="past-seasons" class="bg-base-200">
        <x-PageContainer class="!pb-8">
            <div class="mb-6 text-center" data-aos="fade-up">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-primary-ink">Past seasons</p>
                <h2 class="mt-2 font-heading text-[clamp(2.2rem,9vw,3rem)] text-base-content">Champions <span class="text-primary">so far</span></h2>
            </div>
            <x-SeasonsStage />
            <div class="mt-6 flex flex-wrap justify-center gap-3 text-sm font-bold">
                <a href="{{ route('awards') }}" class="rounded-full border border-base-content/20 px-5 py-2">All awards</a>
                <a href="{{ route('seasons') }}" class="rounded-full border border-base-content/20 px-5 py-2">Past Seasons leaderboard</a>
            </div>
        </x-PageContainer>
    </section>

    <section id="timeline">
        <x-TimelineFull />
    </section>
</div>
