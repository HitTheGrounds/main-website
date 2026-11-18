@php $headingTag = ($bracketCard ?? false) ? 'h4' : 'h3'; @endphp
<article aria-labelledby="match-{{ $match['id'] }}-heading" @class(['tc-match', 'tc-match-live' => $match['status'] === 'live', 'tc-match-published' => $match['result'] !== null])>
    <div class="tc-match-top">
        <span class="tc-match-context">
            {{ $match['group']['name'] ?? $stages[$match['stage']] }}
            @if ($match['display_number'] !== null) · Match {{ $match['display_number'] }} @endif
            @if ($match['bracket_position'] !== null) · Position {{ $match['bracket_position'] }} @endif
        </span>
        <span @class(['tc-status', 'tc-status-live' => $match['status'] === 'live'])>{{ $statuses[$match['status']] }}</span>
    </div>
    <{{ $headingTag }} id="match-{{ $match['id'] }}-heading" tabindex="-1" class="tc-match-heading sr-only">{{ $match['team1']['name'] }} vs {{ $match['team2']['name'] }}</{{ $headingTag }}>
    <dl class="tc-scoreboard">
        @foreach (['team1', 'team2'] as $side)
            @php $innings = $match['result'][$side] ?? null; @endphp
            <div class="tc-team">
                <dt><span>{{ $match[$side]['name'] }}</span></dt>
                <dd>
                    @if ($innings !== null)
                        @if ($innings['runs'] !== null)
                            <span class="tc-score">{{ $innings['runs'] }}@if ($innings['wickets'] !== null)/{{ $innings['wickets'] }}@endif</span>
                            <span class="sr-only"> runs @if ($innings['wickets'] !== null), {{ $innings['wickets'] }} wickets @endif</span>
                        @else
                            <span class="tc-unknown">Runs not recorded</span>
                        @endif
                        @if ($innings['wickets'] === null)<span class="tc-unknown">Wickets not recorded</span>@endif
                        <span class="tc-overs">@if ($innings['overs'] !== null){{ $innings['overs'] }}.{{ $innings['balls'] }} overs @else Overs not recorded @endif</span>
                    @else
                        <span class="tc-score" aria-hidden="true">-</span><span class="sr-only">Score not published</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
    <p class="tc-result">
        @if ($match['result'] !== null)
            {{ $match['result']['description'] }}
        @else
            @if ($match['status'] === 'live') Match in progress. @endif Scores appear after the result is published.
        @endif
    </p>
    <div class="tc-match-meta">
        <p>
            @if ($match['scheduled_at'] !== null)
                <time datetime="{{ $match['scheduled_at'] }}">{{ \Carbon\CarbonImmutable::parse($match['scheduled_at'])->setTimezone(config('tournament.timezone'))->format('j M Y, g:i A') }}</time> (Sri Lanka time)
            @else
                Schedule to be confirmed
            @endif
            @if ($match['venue'] !== null && $match['venue'] !== '') · {{ $match['venue'] }} @endif
        </p>
        <p>{{ $stages[$match['stage']] }} · {{ $match['overs_per_innings'] }} overs per innings · {{ $match['balls_per_over'] }} balls per over</p>
    </div>
</article>
