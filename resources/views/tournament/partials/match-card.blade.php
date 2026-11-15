<article aria-labelledby="match-{{ $match['id'] }}-heading" class="min-w-0 rounded-box border border-base-300 p-6 text-center">
    <div class="mb-3 flex flex-wrap justify-center gap-2 text-sm">
        <span class="badge badge-outline">{{ $statuses[$match['status']] }}</span>
        <span>{{ $stages[$match['stage']] }}@if ($match['group']) · {{ $match['group']['name'] }}@endif</span>
        @if ($match['display_number'] !== null)
            <span>Match {{ $match['display_number'] }}</span>
        @endif
        @if ($match['bracket_position'] !== null)
            <span>Position {{ $match['bracket_position'] }}</span>
        @endif
    </div>
    <h3 id="match-{{ $match['id'] }}-heading" class="break-words font-heading text-2xl">{{ $match['team1']['name'] }} <span class="text-base-content/70">vs</span> {{ $match['team2']['name'] }}</h3>
    <p class="mt-3 text-sm text-base-content/80">{{ $match['overs_per_innings'] }} overs per innings · {{ $match['balls_per_over'] }} balls per over</p>
    <p class="mt-2 text-sm text-base-content/80">
        @if ($match['scheduled_at'] !== null)
            <time datetime="{{ $match['scheduled_at'] }}">{{ \Carbon\CarbonImmutable::parse($match['scheduled_at'])->setTimezone(config('tournament.timezone'))->format('j M Y, g:i A') }}</time> (Sri Lanka time)
        @else
            Schedule to be confirmed
        @endif
    </p>
    @if ($match['venue'] !== null && $match['venue'] !== '')
        <p class="mt-2 break-words text-sm">{{ $match['venue'] }}</p>
    @endif

    @if ($match['result'] !== null)
        <dl class="mt-5 grid gap-4 border-t border-base-300 pt-5 sm:grid-cols-2">
            @foreach (['team1', 'team2'] as $side)
                @php $innings = $match['result'][$side]; @endphp
                <div class="min-w-0">
                    <dt class="break-words font-semibold">{{ $match[$side]['name'] }}</dt>
                    <dd class="mt-1">
                        @if ($innings['runs'] !== null)
                            <span class="text-2xl font-bold tabular-nums">{{ $innings['runs'] }}@if ($innings['wickets'] !== null)/{{ $innings['wickets'] }}@endif</span>
                            <span class="sr-only">runs@if ($innings['wickets'] !== null), {{ $innings['wickets'] }} wickets@endif</span>
                        @else
                            <span>Runs not recorded</span>
                        @endif
                        @if ($innings['wickets'] === null)
                            <span class="block text-sm text-base-content/70">Wickets not recorded</span>
                        @endif
                        <span class="block text-sm text-base-content/70">
                            @if ($innings['overs'] !== null)
                                {{ $innings['overs'] }}.{{ $innings['balls'] }} overs
                            @else
                                Overs not recorded
                            @endif
                        </span>
                    </dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-5 break-words font-semibold">{{ $match['result']['description'] }}</p>
    @else
        <p class="mt-5 text-base-content/70">@if ($match['status'] === 'live') Match in progress. @endif Scores appear after the result is published.</p>
    @endif
</article>
