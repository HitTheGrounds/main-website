@if ($filterErrors)
    <div role="alert" class="mt-6 rounded-box border border-base-300 p-6 text-center">
        <p>Select valid player stats filters and try again.</p>
        <a href="{{ route('tournament.index', ['tab' => 'stats']) }}" class="btn btn-outline mt-4">Reset stats filters</a>
    </div>
@else
    @php
        $categories = ['runs' => 'Most runs', 'wickets' => 'Most wickets', 'sixes' => 'Most sixes',
            'fours' => 'Most fours', 'dot_balls' => 'Dot ball dominators', 'highest_innings' => 'Highest individual innings'];
        $figures = ['runs' => 'Runs', 'wickets' => 'Wickets', 'sixes' => 'Sixes', 'fours' => 'Fours',
            'dot_balls' => 'Dot balls', 'highest_innings' => 'Runs in one innings'];
        $category = $rankingData['category'];
        $gender = $rankingData['gender'];
        $joint = count($rankingData['leader_ids']) > 1;
    @endphp
    <details class="tc-filter">
        <summary>Filter player stats <span>{{ $categories[$category] }} · {{ $gender === 'All' ? 'All players' : $gender }}</span></summary>
        <form method="GET" action="{{ route('tournament.index') }}" aria-label="Choose player stats" class="tc-form">
            <input type="hidden" name="tab" value="stats">
            <div>
                <label for="stats-category" class="mb-2 block font-semibold">Category</label>
                <select id="stats-category" name="category" class="select w-full">
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="stats-gender" class="mb-2 block font-semibold">Players</label>
                <select id="stats-gender" name="gender" class="select w-full">
                    @foreach (['All', 'Male', 'Female'] as $value)
                        <option value="{{ $value }}" @selected($gender === $value)>{{ $value }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tc-form-actions">
                <button type="submit" class="btn btn-primary">Show stats</button>
                <a href="{{ route('tournament.index', ['tab' => 'stats']) }}" class="btn btn-outline">Reset stats filters</a>
            </div>
        </form>
    </details>

    <section aria-labelledby="player-ranking-heading" class="tc-panel">
        <h3 id="player-ranking-heading" class="text-center font-heading text-3xl">{{ $categories[$category] }}</h3>
        <p class="tc-ranking-context">{{ $gender === 'All' ? 'All players' : $gender.' players' }}</p>
        <p class="tc-ranking-context">Top five, including ties. Leaders are provisional.</p>

        @if ($rankingData['entries'] === [])
            <p class="mt-6 text-center">No published figures are available for this selection yet.</p>
        @else
            @if ($rankingData['leader_ids'] === [])
                <p class="mt-4 text-center font-semibold">No title holder yet. All recorded figures in this category are zero.</p>
            @endif
            <div role="region" aria-label="Player rankings" tabindex="0" class="mt-5 overflow-x-auto focus-visible:outline-2 focus-visible:outline-primary">
                <table class="tc-table tc-rankings">
                    <caption class="sr-only">{{ $categories[$category] }}, {{ $gender }} players</caption>
                    <thead><tr><th scope="col">Rank</th><th scope="col">Player</th><th scope="col">Team</th><th scope="col">{{ $figures[$category] }}</th><th scope="col">Recorded match entries</th></tr></thead>
                    <tbody>
                        @foreach ($rankingData['entries'] as $entry)
                            @php $leader = in_array($entry['player_id'], $rankingData['leader_ids'], true); @endphp
                            <tr @class(['tc-player-leader' => $leader])>
                                <td class="font-semibold">{{ $entry['rank'] }}</td>
                                <th scope="row" class="min-w-40 max-w-72 break-words">
                                    {{ $entry['player_name'] }}
                                    <span class="tc-small-record">
                                        <span>{{ $entry['team_name'] }}</span>
                                        <span>{{ $entry['recorded_count'] }} recorded {{ $entry['recorded_count'] === 1 ? 'match' : 'matches' }}</span>
                                    </span>
                                    @if ($leader)
                                        <span class="tc-ranking-context">{{ $joint ? 'Joint leader' : 'Leader' }} (provisional)</span>
                                        @if ($gender === 'All' && in_array($category, ['runs', 'wickets'], true))
                                            <span class="badge badge-outline mt-1 h-auto whitespace-normal py-1 text-xs font-normal">{{ $category === 'runs' ? 'Orange Cap' : 'Purple Cap' }} (provisional)</span>
                                        @endif
                                    @endif
                                </th>
                                <td class="min-w-32 max-w-64 break-words">{{ $entry['team_name'] }}</td>
                                <td class="text-xl font-bold tabular-nums">{{ $entry['value'] }}</td>
                                <td>{{ $entry['recorded_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-center text-sm text-base-content/80">Recorded match entries count only entries containing this figure. Other match entries may be missing. Super Over performances are excluded.</p>
        @endif
        <details class="tc-notes">
            <summary>How these rankings work</summary>
        <p class="mx-auto mt-3 max-w-[56ch] text-center text-base-content/80">Rankings reflect published player stats. Some match entries may be incomplete. Leaders are provisional.</p>
        <p class="mt-3 text-center text-sm">Top five players, including everyone tied at the fifth player's figure.</p>
        <p class="mt-3 text-center text-sm text-base-content/80">{{ $rankingData['coverage']['players_with_recorded_metric'] }} of {{ $rankingData['coverage']['players_with_published_entries'] }} players with eligible published entries have a recorded figure in this category. These counts do not measure the whole tournament's player coverage.</p>

        </details>
    </section>
@endif
