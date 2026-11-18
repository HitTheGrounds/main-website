@php
    // NRR is stored to 6 decimal places and ranking uses that full value; show 4.
    $formatNrr = fn (?string $nrr) => $nrr === null ? 'Not available'
        : (string) \Brick\Math\BigDecimal::of($nrr)->toScale(4, \Brick\Math\RoundingMode::HALF_UP);
@endphp
@if ($tableData['groups'] === [])
    <p class="mt-6 rounded-box border border-base-300 p-6 text-center">Groups have not been announced yet.</p>
@else
    <nav aria-label="Choose a group" class="tc-jump">
        @foreach ($tableData['groups'] as $group)
            <a class="btn btn-outline h-auto min-h-10 max-w-full whitespace-normal py-2" href="#group-{{ $group['id'] }}"><span class="min-w-0 break-words">{{ $group['name'] }}</span></a>
        @endforeach
        <a class="btn btn-outline" href="#runner-comparison">Runner-ups</a>
    </nav>
    <details class="tc-standings-guide">
        <summary>How standings are ranked</summary>
        <p>Positions use points, then net run rate (NRR). Equal records share a position. Qualification is confirmed separately by the committee.</p>
    </details>
    <div class="tc-groups">
        @foreach ($tableData['groups'] as $group)
            <section id="group-{{ $group['id'] }}" tabindex="-1" aria-labelledby="group-{{ $group['id'] }}-heading" class="tc-panel">
                <h3 id="group-{{ $group['id'] }}-heading" class="break-words text-center font-heading text-3xl">{{ $group['name'] }}</h3>
                @if ($group['teams'] === [])
                    <p class="mt-4 text-center">Teams have not been assigned to this group yet.</p>
                @else
                    <div role="region" aria-label="{{ $group['name'] }} standings" tabindex="0" class="mt-4 overflow-x-auto focus-visible:outline-2 focus-visible:outline-primary">
                        <table class="tc-table tc-standings">
                            <caption class="sr-only">{{ $group['name'] }} standings</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Position</th><th scope="col">Team</th><th scope="col">Played</th>
                                    <th scope="col">Wins</th><th scope="col">Losses</th><th scope="col">Ties</th>
                                    <th scope="col">No results</th><th scope="col">Points</th><th scope="col">NRR</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group['teams'] as $team)
                                    <tr>
                                        <td>{{ $team['rank'] }}</td>
                                        <th scope="row" class="min-w-40 max-w-72 break-words">
                                            <span class="tc-table-team">
                                            <x-tournament.team-badge :name="$team['name']" />
                                            <span class="tc-table-team-text">
                                            {{ $team['name'] }}
                                            <span class="tc-small-record">
                                                <span><abbr title="Played">P</abbr> {{ $team['played'] }}</span>
                                                <span><abbr title="Wins">W</abbr> {{ $team['wins'] }}</span>
                                                <span><abbr title="Losses">L</abbr> {{ $team['losses'] }}</span>
                                                <span><abbr title="Ties">T</abbr> {{ $team['ties'] }}</span>
                                                <span><abbr title="No results">NR</abbr> {{ $team['no_results'] }}</span>
                                            </span>
                                            @if ($team['qualified'])
                                                <span class="tc-qualification">Qualification confirmed</span>
                                            @endif
                                            </span>
                                            </span>
                                        </th>
                                        <td>{{ $team['played'] }}</td><td>{{ $team['wins'] }}</td><td>{{ $team['losses'] }}</td>
                                        <td>{{ $team['ties'] }}</td><td>{{ $team['no_results'] }}</td>
                                        <td class="font-bold">{{ $team['points'] }}</td><td>{{ $formatNrr($team['nrr']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <details class="tc-notes">
                    <summary>Results & qualification details</summary>
                <p class="mt-4 text-center text-sm">{{ $group['coverage']['published'] }} of {{ $group['coverage']['expected'] }} possible group fixtures have published results.</p>
                @if (! $group['coverage']['complete'])
                    <p class="mt-2 text-center text-sm text-base-content/80">Group results are incomplete. {{ $group['coverage']['missing'] }} fixtures not scheduled; {{ $group['coverage']['unpublished'] }} scheduled fixtures without published results.</p>
                @endif
                @if ($group['undefined_nrr'])
                    <p class="mt-2 text-center text-sm text-base-content/80">NRR is not available where eligible innings figures have not been recorded.</p>
                @endif
                @if ($group['tied'])
                    <p class="mt-2 text-center text-sm text-base-content/80">Some teams share a position. Remaining qualification ties are decided by the committee.</p>
                @endif
                </details>
            </section>
        @endforeach
    </div>

    <section id="runner-comparison" tabindex="-1" aria-labelledby="runner-guidance-heading" class="tc-panel mt-8">
        <h3 id="runner-guidance-heading" class="text-center font-heading text-3xl">Runner-up comparison</h3>
        <p class="mx-auto mt-3 max-w-[56ch] text-center text-base-content/80">This comparison uses points, then NRR. It does not confirm qualification. Teams sharing the second position, including joint leaders, are included.</p>
        @if ($tableData['unequal_groups'])
            <p class="mt-3 text-center font-semibold">Groups have different team counts. Cross-group comparisons need committee review.</p>
        @endif
        @if ($tableData['runner_up_guidance'] === [])
            <p class="mt-4 text-center">No runner-up comparison is available yet.</p>
        @else
            <div role="region" aria-label="Runner-up comparison" tabindex="0" class="mt-4 overflow-x-auto focus-visible:outline-2 focus-visible:outline-primary">
                <table class="tc-table tc-runners">
                    <caption class="sr-only">Runner-up candidates, not confirmed qualification</caption>
                    <thead><tr><th scope="col">Position</th><th scope="col">Team</th><th scope="col">Group</th><th scope="col">Played</th><th scope="col">Points</th><th scope="col">NRR</th><th scope="col">Comparison</th></tr></thead>
                    <tbody>
                        @foreach ($tableData['runner_up_guidance'] as $team)
                            <tr>
                                <td>{{ $team['rank'] }}</td><th scope="row" class="min-w-40 max-w-72 break-words">
                                    <span class="tc-table-team">
                                    <x-tournament.team-badge :name="$team['name']" />
                                    <span class="tc-table-team-text">
                                    {{ $team['name'] }}
                                    <span class="tc-small-record">
                                        <span>{{ $team['group_name'] }}</span>
                                        <span>Played {{ $team['played'] }}</span>
                                        <span>{{ $team['provisional'] ? 'Provisional' : 'Group results complete' }}</span>
                                    </span>
                                    </span>
                                    </span>
                                </th>
                                <td class="break-words">{{ $team['group_name'] }}</td><td>{{ $team['played'] }}</td><td>{{ $team['points'] }}</td>
                                <td>{{ $formatNrr($team['nrr']) }}</td><td>{{ $team['provisional'] ? 'Provisional' : 'Group results complete' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-center text-sm text-base-content/80">Incomplete results, tied positions, unavailable NRR or unequal group sizes make the comparison provisional. Abandoned and no-result matches do not contribute to NRR.</p>
        @endif
    </section>
@endif
