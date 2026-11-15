<x-layouts.public title="Tournament" description="Match results, group standings, knockout bracket and player leaders for Hit the Grounds.">
    <x-PageContainer>
        <header class="mb-10 text-center md:mb-14">
            <p class="mb-3 text-sm font-semibold uppercase tracking-widest text-base-content/70">Hit the Grounds {{ $year }}</p>
            <h1 class="font-heading text-[clamp(2.75rem,8vw,5.5rem)]"><span class="text-primary">Tournament</span> centre</h1>
            <p class="mx-auto mt-4 max-w-[56ch] text-base-content/80">Follow the matches, group standings and player leaders. Refresh the page to see newly published results.</p>
        </header>

        <nav aria-label="Tournament sections" class="mb-8 grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($tabs as $key => $section)
                <a href="{{ route('tournament.index', ['tab' => $key]) }}"
                   @if ($tab === $key) aria-current="page" @endif
                   @class(['btn w-full focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary', 'btn-primary' => $tab === $key, 'btn-outline' => $tab !== $key])>
                    {{ $section['label'] }}
                </a>
            @endforeach
        </nav>

        <section aria-labelledby="tournament-section-heading" class="rounded-box border border-base-300 bg-base-100 p-6 text-center sm:p-8">
            <h2 id="tournament-section-heading" class="font-heading text-3xl sm:text-4xl">{{ $tabs[$tab]['label'] }}</h2>
            <p class="mx-auto mt-3 max-w-[56ch] text-base-content/80">{{ $tabs[$tab]['description'] }}</p>
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
            @if ($tab === 'stats')
                @include('tournament.partials.publication-time', ['publicationLabel' => 'Latest player stats publication (whole tournament)', 'publicationTime' => $rankingData['publication_times']['player_stats']])
                @include('tournament.partials.publication-time', ['publicationLabel' => 'Latest match result publication (whole tournament)', 'publicationTime' => $rankingData['publication_times']['match_results']])
            @else
                @php $pageData = match ($tab) { 'matches' => $matchData, 'table' => $tableData, 'bracket' => $bracketData }; @endphp
                @include('tournament.partials.publication-time', ['publicationLabel' => $tab === 'matches' ? 'Latest match result publication for these filters' : 'Latest match result publication in this view', 'publicationTime' => $pageData['latest_result_published_at']])
            @endif
            <p class="mt-3 text-center text-sm text-base-content/80">Refresh this page for the latest published results. Changes appear when you refresh or open another page.</p>
        @endif
    </x-PageContainer>
</x-layouts.public>
