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
    </nav>
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
                                            <span class="tc-table-team-name">{{ $team['name'] }}@if ($team['qualified'])<span class="tc-q" title="Qualified" aria-hidden="true">Q</span><span class="sr-only"> (qualified)</span>@endif</span>
                                            <span class="tc-small-record">
                                                <span><abbr title="Played">P</abbr> {{ $team['played'] }}</span>
                                                <span><abbr title="Wins">W</abbr> {{ $team['wins'] }}</span>
                                                <span><abbr title="Losses">L</abbr> {{ $team['losses'] }}</span>
                                                <span><abbr title="Ties">T</abbr> {{ $team['ties'] }}</span>
                                                <span><abbr title="No results">NR</abbr> {{ $team['no_results'] }}</span>
                                            </span>
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
            </section>
        @endforeach
    </div>
@endif
