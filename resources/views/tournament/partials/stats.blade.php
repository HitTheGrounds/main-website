@if ($filterErrors)
    <div role="alert" class="mt-6 rounded-box border border-base-300 p-6 text-center">
        <p>This stats selection is unavailable.</p>
        <a href="{{ route('tournament.index', ['tab' => 'stats']) }}" class="btn btn-outline mt-4">Back to Stats</a>
    </div>
@else
    @php
        $categories = ['runs' => 'Most runs', 'wickets' => 'Most wickets', 'sixes' => 'Most sixes',
            'fours' => 'Most fours', 'dot_balls' => 'Dot ball dominators', 'highest_innings' => 'Highest individual innings'];
        $figures = ['runs' => 'Runs', 'wickets' => 'Wickets', 'sixes' => 'Sixes', 'fours' => 'Fours',
            'dot_balls' => 'Dot balls', 'highest_innings' => 'Runs in one innings'];
        $category = $rankingData['category'];
    @endphp
    <nav aria-label="Stats category" class="tc-status-tabs tc-stats-categories">
        @foreach (['runs' => 'Runs', 'wickets' => 'Wickets', 'sixes' => 'Sixes', 'fours' => 'Fours',
            'dot_balls' => 'Dot balls', 'highest_innings' => 'Highest innings'] as $value => $label)
            <a href="{{ route('tournament.index', ['tab' => 'stats', 'category' => $value]) }}"
                @if ($category === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <section aria-labelledby="player-ranking-heading" class="tc-panel tc-stats-panel">

        @if ($rankingData['entries'] === [])
            <p class="mt-6 text-center">No published figures are available for this selection yet.</p>
        @else
            @if ($rankingData['leader_ids'] === [])
                <p class="mt-4 text-center font-semibold">No title holder yet. All recorded figures in this category are zero.</p>
            @endif
            <div role="region" aria-label="Player rankings" tabindex="0" class="overflow-x-auto">
                <table class="tc-stats-leaderboard">
                    <caption class="sr-only">{{ $categories[$category] }}, All players</caption>
                    <tbody>
                        @foreach ($rankingData['entries'] as $entry)
                            @php
                                $cap = in_array($entry['player_id'], $rankingData['leader_ids'], true)
                                    ? match ($category) {
                                        'runs' => 'Orange',
                                        'wickets' => 'Purple',
                                        default => null,
                                    }
                                    : null;
                            @endphp
                            <tr>
                                <td class="font-semibold">{{ $entry['rank'] }}</td>
                                <td class="tc-stats-logo">
                                    <x-tournament.team-badge :name="$entry['team_name']" />
                                </td>
                                <th scope="row" class="break-words">
                                    {{ $entry['player_name'] }}
                                    <span class="tc-stats-team">{{ $entry['team_name'] }}</span>
                                </th>
                                <td class="text-xl font-bold tabular-nums">
                                    <span class="tc-stats-score">
                                        @if ($cap)
                                            <img src="{{ asset('award/'.strtolower($cap).'_cap.avif') }}" alt="{{ $cap }} Cap" width="32" height="32">
                                        @endif
                                        <span>{{ $entry['value'] }}</span>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-center text-sm text-base-content/80">Super Over performances are excluded.</p>
        @endif
        <details class="tc-notes">
            <summary>How these rankings work</summary>
        <p class="mx-auto mt-3 max-w-[56ch] text-center text-base-content/80">Rankings reflect published player stats. Some match entries may be incomplete. Leaders are provisional.</p>
        <p class="mt-3 text-center text-sm">Top five players, including everyone tied at the fifth player's figure.</p>
        <p class="mt-3 text-center text-sm text-base-content/80">{{ $rankingData['coverage']['players_with_recorded_metric'] }} of {{ $rankingData['coverage']['players_with_published_entries'] }} players with eligible published entries have a recorded figure in this category. These counts do not measure the whole tournament's player coverage.</p>

        </details>
    </section>
@endif
