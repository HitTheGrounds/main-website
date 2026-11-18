@php
    $stages = ['G' => 'Group stage', 'QF' => 'Quarter-final', 'SF' => 'Semi-final', 'F' => 'Final'];
    $roundHeadings = ['QF' => 'Quarterfinals', 'SF' => 'Semifinals', 'F' => 'Finals'];
    $statuses = ['upcoming' => 'Upcoming', 'live' => 'In progress', 'finished' => 'Finished'];
    $sources = [];
    $quarterColumns = [];
    $remainingColumns = [1, 1, 5, 5];
    foreach ($bracketData['rounds']['SF'] as $semi) {
        $column = $semi['position'] === 1 ? 1 : 5;
        foreach (['team1', 'team2'] as $side) {
            $source = $semi[$side]['source_match_id'] ?? null;
            if ($source !== null) {
                $quarterColumns[$source] = $column;
                unset($remainingColumns[array_search($column, $remainingColumns, true)]);
            }
        }
    }
    $quarterRows = [1 => 2, 5 => 2];
    foreach ($bracketData['rounds'] as $stage => $positions) {
        foreach ($positions as $position) {
            if ($position['match'] !== null) {
                $sources[$position['match']['id']] = $stages[$stage].' '.$position['position'];
            }
        }
    }
@endphp
<div class="tc-playoff-scroll" role="region" aria-label="Playoff bracket">
    <div class="tc-playoffs" data-playoff-bracket>
        <canvas class="tc-playoff-lines" aria-hidden="true"></canvas>
        @foreach (['SF', 'QF'] as $stage)
            <div class="tc-round-head tc-playoff-mirror" data-stage="{{ $stage }}" aria-hidden="true">
                <span>{{ $roundHeadings[$stage] }}</span>
            </div>
        @endforeach
        @foreach ($bracketData['rounds'] as $stage => $positions)
            <section id="round-{{ $stage }}" tabindex="-1" aria-labelledby="round-{{ $stage }}-heading" class="tc-playoff-round" data-stage="{{ $stage }}">
                <div class="tc-round-head">
                    <h3 id="round-{{ $stage }}-heading">{{ $roundHeadings[$stage] }}</h3>
                </div>
                <div class="tc-playoff-matches">
                    @foreach ($positions as $position)
                        @php
                            $matchId = $position['match']['id'] ?? null;
                            $column = match ($stage) {
                                'QF' => $quarterColumns[$matchId] ?? array_shift($remainingColumns),
                                'SF' => $position['position'] === 1 ? 2 : 4,
                                'F' => 3,
                            };
                            $row = $stage === 'QF' ? $quarterRows[$column]++ : '2 / span 2';
                            $mobileRow = match ($stage) {
                                'QF' => (2 * ($row - 2) + ($column === 5 ? 6 : 2)).' / span 2',
                                'SF' => ($column === 2 ? 2 : 6).' / span 4',
                                'F' => '2 / span 8',
                            };
                        @endphp
                        <div class="tc-playoff-slot" style="--column: {{ $column }}; --row: {{ $row }}; --mobile-row: {{ $mobileRow }}"
                            data-match="{{ $matchId }}" data-sources="{{ implode(' ', array_filter([$position['team1']['source_match_id'] ?? null, $position['team2']['source_match_id'] ?? null])) }}">
                            @if ($position['match'] !== null)
                                @include('tournament.partials.match-card', ['match' => $position['match'], 'bracketCard' => true, 'bracketTeams' => $position])
                                @if ($stage !== 'QF')
                                @endif
                            @else
                                <div class="tc-empty-slot">
                                    <h4 class="font-heading text-2xl">{{ $stages[$stage] }} {{ $position['position'] }}</h4>
                                    <p class="mt-3">To be confirmed</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
