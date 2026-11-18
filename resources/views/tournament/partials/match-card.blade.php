@php
    $bracketCard ??= false;
    $headingTag = $bracketCard ? 'h4' : 'h3';
    $scoreboardTag = $bracketCard ? 'div' : 'dl';
    $nameTag = $bracketCard ? 'span' : 'dt';
    $scoreTag = $bracketCard ? 'div' : 'dd';
@endphp
<article aria-labelledby="match-{{ $match['id'] }}-heading" @class(['tc-match', 'tc-match-live' => $match['status'] === 'live', 'tc-match-published' => $match['result'] !== null])>
    <{{ $headingTag }} id="match-{{ $match['id'] }}-heading" tabindex="-1" class="tc-match-heading sr-only">{{ $match['team1']['name'] }} vs {{ $match['team2']['name'] }}</{{ $headingTag }}>
    <{{ $scoreboardTag }} class="tc-scoreboard">
        @foreach (['team1', 'team2'] as $side)
            @php
                $innings = $match['result'][$side] ?? null;
                $winnerId = $match['result']['winner_id'] ?? null;
                $hasBracketWinner = $bracketCard && $winnerId !== null;
                $isWinner = $match[$side]['id'] === $winnerId;
            @endphp
            <div @class(['tc-team', 'tc-team-eliminated' => $hasBracketWinner && ! $isWinner, 'tc-team-winner' => $hasBracketWinner && $isWinner])>
                @if ($bracketCard)
                    <span class="tc-team-logo" aria-hidden="true">
                        @if ($logo = $bracketTeams[$side]['logo_url'] ?? null)
                            <img src="{{ $logo }}" alt="">
                        @endif
                    </span>
                    <div class="tc-bracket-team-details">
                @endif
                <{{ $nameTag }} class="tc-team-name">{{ $match[$side]['name'] }}</{{ $nameTag }}>
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
