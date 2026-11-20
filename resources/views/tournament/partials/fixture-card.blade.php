{{-- Matches tab card. Kept separate from match-card.blade.php (used by the bracket)
     with its own tc-fixture-* classes, so the two cards can change independently.
     Styles: resources/css/tournament-matches.css --}}
@php
    $result = $match['result'];
    $winnerId = $result['winner_id'] ?? null;
@endphp
<article aria-labelledby="match-{{ $match['id'] }}-heading" @class(['tc-fixture', 'tc-fixture-'.$match['status'], 'tc-fixture-published' => $result !== null])>
    <header class="tc-fixture-top">
        <span class="tc-fixture-context">
            {{ $match['group']['name'] ?? $stages[$match['stage']] }}
            @if ($match['display_number'] !== null)<span aria-hidden="true"> · </span><span class="sr-only">, </span>Match {{ $match['display_number'] }}@endif
            @if ($match['bracket_position'] !== null)<span aria-hidden="true"> · </span><span class="sr-only">, </span>Position {{ $match['bracket_position'] }}@endif
        </span>
        <span class="tc-fixture-status tc-fixture-status-{{ $match['status'] }}">{{ $statuses[$match['status']] }}</span>
    </header>
    <h3 id="match-{{ $match['id'] }}-heading" tabindex="-1" class="tc-fixture-heading sr-only">{{ $match['team1']['name'] }} vs {{ $match['team2']['name'] }}</h3>
    <dl class="tc-fixture-board">
        @foreach (['team1', 'team2'] as $side)
            @php
                $innings = $result[$side] ?? null;
                $isWinner = $winnerId !== null && $winnerId === $match[$side]['id'];
            @endphp
            <div @class(['tc-fixture-team', 'tc-fixture-team-won' => $isWinner, 'tc-fixture-team-lost' => $winnerId !== null && ! $isWinner])>
                <dt>
                    <x-tournament.team-badge :name="$match[$side]['name']" />
                    <span class="tc-fixture-name">{{ $match[$side]['name'] }}</span>
                    @if ($isWinner)<span class="sr-only">(winner)</span>@endif
                </dt>
                <dd>
                    @if ($innings !== null && $innings['runs'] === null && $innings['wickets'] === null && $innings['overs'] === null)
                        <span class="tc-fixture-unknown">Score not recorded</span>
                    @elseif ($innings !== null)
                        <span class="tc-fixture-score-line">
                            @if ($innings['runs'] !== null)
                                <span class="tc-fixture-score">{{ $innings['runs'] }}@if ($innings['wickets'] !== null)/{{ $innings['wickets'] }}@endif</span>
                                <span class="sr-only"> runs @if ($innings['wickets'] !== null), {{ $innings['wickets'] }} wickets @endif</span>
                            @endif
                            @if ($innings['overs'] !== null)
                                <span class="tc-fixture-overs">({{ $innings['overs'] }}.{{ $innings['balls'] }}<span class="sr-only"> overs</span>)</span>
                            @endif
                        </span>
                        @if ($innings['runs'] === null)<span class="tc-fixture-unknown">Runs not recorded</span>@endif
                        @if ($innings['wickets'] === null)<span class="tc-fixture-unknown">Wickets not recorded</span>@endif
                        @if ($innings['overs'] === null)<span class="tc-fixture-unknown">Overs not recorded</span>@endif
                    @else
                        <span class="tc-fixture-pending" aria-hidden="true">-</span><span class="sr-only">Score not published</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>
    <p class="tc-fixture-result">
        @if ($result !== null)
            {{ $result['description'] }}
        @elseif ($match['status'] === 'live')
            Match in progress. Scores appear after the result is published.
        @else
            Yet to start. Scores appear after the result is published.
        @endif
    </p>
    @if ($result !== null && $match['status'] === 'finished')
        <button type="button" class="tc-scorecard-open" data-scorecard="{{ route('tournament.scorecard', $match['id']) }}"
                aria-haspopup="dialog" aria-label="View scorecard for {{ $match['team1']['name'] }} vs {{ $match['team2']['name'] }}">View scorecard &rsaquo;</button>
    @endif
</article>
