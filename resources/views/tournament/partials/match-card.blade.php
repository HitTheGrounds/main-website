@php
    $headingTag = ($bracketCard ?? false) ? 'h4' : 'h3';
    $result = $match['result'];
    $winnerId = $result['winner_id'] ?? null;
@endphp
<article aria-labelledby="match-{{ $match['id'] }}-heading" @class(['tc-match', 'tc-match-'.$match['status'], 'tc-match-published' => $result !== null])>
    <header class="tc-match-top">
        <span class="tc-match-context">
            {{ $match['group']['name'] ?? $stages[$match['stage']] }}
            @if ($match['display_number'] !== null)<span aria-hidden="true"> · </span><span class="sr-only">, </span>Match {{ $match['display_number'] }}@endif
            @if ($match['bracket_position'] !== null)<span aria-hidden="true"> · </span><span class="sr-only">, </span>Position {{ $match['bracket_position'] }}@endif
        </span>
        <span class="tc-status tc-status-{{ $match['status'] }}">{{ $statuses[$match['status']] }}</span>
    </header>
    <{{ $headingTag }} id="match-{{ $match['id'] }}-heading" tabindex="-1" class="tc-match-heading sr-only">{{ $match['team1']['name'] }} vs {{ $match['team2']['name'] }}</{{ $headingTag }}>
    <dl class="tc-scoreboard">
        @foreach (['team1', 'team2'] as $side)
            @php
                $innings = $result[$side] ?? null;
                $isWinner = $winnerId !== null && $winnerId === $match[$side]['id'];
            @endphp
            <div @class(['tc-team', 'tc-team-winner' => $isWinner, 'tc-team-loser' => $winnerId !== null && ! $isWinner])>
                <dt>
                    <x-tournament.team-badge :name="$match[$side]['name']" />
                    <span class="tc-team-name">{{ $match[$side]['name'] }}</span>
                    @if ($isWinner)<span class="sr-only">(winner)</span>@endif
                </dt>
                <dd>
                    @if ($innings !== null && $innings['runs'] === null && $innings['wickets'] === null && $innings['overs'] === null)
                        <span class="tc-unknown">Score not recorded</span>
                    @elseif ($innings !== null)
                        <span class="tc-score-line">
                            @if ($innings['runs'] !== null)
                                <span class="tc-score">{{ $innings['runs'] }}@if ($innings['wickets'] !== null)/{{ $innings['wickets'] }}@endif</span>
                                <span class="sr-only"> runs @if ($innings['wickets'] !== null), {{ $innings['wickets'] }} wickets @endif</span>
                            @endif
                            @if ($innings['overs'] !== null)
                                <span class="tc-overs">({{ $innings['overs'] }}.{{ $innings['balls'] }}<span class="sr-only"> overs</span>)</span>
                            @endif
                        </span>
                        @if ($innings['runs'] === null)<span class="tc-unknown">Runs not recorded</span>@endif
                        @if ($innings['wickets'] === null)<span class="tc-unknown">Wickets not recorded</span>@endif
                        @if ($innings['overs'] === null)<span class="tc-unknown">Overs not recorded</span>@endif
                    @else
                        <span class="tc-score-pending" aria-hidden="true">-</span><span class="sr-only">Score not published</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
    <p class="tc-result">
        @if ($result !== null)
            {{ $result['description'] }}
        @elseif ($match['status'] === 'live')
            Match in progress. Scores appear after the result is published.
        @else
            Yet to start. Scores appear after the result is published.
        @endif
    </p>
</article>
