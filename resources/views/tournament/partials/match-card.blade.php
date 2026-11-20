@php
    $bracketCard ??= false;
    $headingTag = $bracketCard ? 'h4' : 'h3';
    $scoreboardTag = $bracketCard ? 'div' : 'dl';
    $nameTag = $bracketCard ? 'span' : 'dt';
    $scoreTag = $bracketCard ? 'div' : 'dd';
    // Bracket only: draw the winner on the side facing the next round's card.
    $winnerSide ??= null;
    $sideOrder = ['team1', 'team2'];
    $bracketWinnerId = $bracketCard ? ($match['result']['winner_id'] ?? null) : null;
    if ($bracketWinnerId !== null && $winnerSide !== null) {
        $winnerKey = $match['team1']['id'] === $bracketWinnerId ? 'team1' : 'team2';
        $loserKey = $winnerKey === 'team1' ? 'team2' : 'team1';
        $sideOrder = $winnerSide === 'top' ? [$winnerKey, $loserKey] : [$loserKey, $winnerKey];
    }
@endphp
<article aria-labelledby="match-{{ $match['id'] }}-heading" @class(['tc-match', 'tc-match-live' => $match['status'] === 'live', 'tc-match-published' => $match['result'] !== null])>
    <{{ $headingTag }} id="match-{{ $match['id'] }}-heading" tabindex="-1" class="tc-match-heading sr-only">{{ $match['team1']['name'] }} vs {{ $match['team2']['name'] }}</{{ $headingTag }}>
    <{{ $scoreboardTag }} class="tc-scoreboard">
        @foreach ($sideOrder as $side)
            @php
                $innings = $match['result'][$side] ?? null;
                $winnerId = $match['result']['winner_id'] ?? null;
                $hasBracketWinner = $bracketCard && $winnerId !== null;
                $isWinner = $match[$side]['id'] === $winnerId;
            @endphp
            @php($isChampion = $bracketCard && $isWinner && $winnerId !== null && ($match['stage'] ?? null) === 'F')
            {{-- data-from: the earlier match this team came through, so bracket lines can land on this row. --}}
            <div @class(['tc-team', 'tc-team-eliminated' => $hasBracketWinner && ! $isWinner, 'tc-team-winner' => $hasBracketWinner && $isWinner, 'tc-team-champion' => $isChampion])
                @if ($bracketCard) data-from="{{ $bracketTeams[$side]['source_match_id'] ?? '' }}" @endif>
                @if ($bracketCard)
                    {{-- Logo chosen by App\Support\TeamLogo, the same lookup as every other tab. --}}
                    @php($logo = $bracketTeams[$side]['logo'] ?? \App\Support\TeamLogo::resolve($match[$side]['name']))
                    <span @class(['tc-team-logo', 'tc-team-logo-text' => $logo['type'] !== 'logo']) aria-hidden="true">
                        @if ($logo['type'] === 'logo')
                            <img src="{{ $logo['url'] }}" alt="" decoding="async">
                        @elseif ($logo['type'] === 'cse')
                            <span class="tc-team-logo-cse">CSE<i></i>{{ $logo['batch'] }}</span>
                        @else
                            {{ $logo['initials'] }}
                        @endif
                    </span>
                    <div class="tc-bracket-team-details">
                @endif
                <{{ $nameTag }} class="tc-team-name">@if ($isChampion)<svg class="tc-champion-cup" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4h8v5a4 4 0 0 1-8 0Z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8.5 20h7M10 17h4"/></svg>@endif{{ $match[$side]['name'] }}@if ($isChampion)<span class="sr-only"> (champion)</span>@elseif ($hasBracketWinner && $isWinner)<span class="sr-only"> (winner)</span>@endif</{{ $nameTag }}>
                <{{ $scoreTag }} class="tc-team-score">
                    @if ($innings !== null)
                        @if ($innings['runs'] !== null)
                            <span class="tc-score">{{ $innings['runs'] }}@if ($innings['wickets'] !== null)/{{ $innings['wickets'] }}@endif</span>
                            <span class="sr-only"> runs @if ($innings['wickets'] !== null), {{ $innings['wickets'] }} wickets @endif</span>
                        @else
                            <span class="tc-unknown">Runs not recorded</span>
                        @endif
                        @if ($innings['wickets'] === null)<span class="tc-unknown">Wickets not recorded</span>@endif
                        @unless ($bracketCard)
                            <span class="tc-overs">@if ($innings['overs'] !== null){{ $innings['overs'] }}.{{ $innings['balls'] }} overs @else Overs not recorded @endif</span>
                        @endunless
                    @else
                        <span class="tc-score" aria-hidden="true">-</span><span class="sr-only">Score not published</span>
                    @endif
                </{{ $scoreTag }}>
                @if ($bracketCard)
                    </div>
                @endif
            </div>
        @endforeach
    </{{ $scoreboardTag }}>
</article>
