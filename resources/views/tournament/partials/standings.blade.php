@if ($tableData['groups'] === [])
    <p class="mt-6 rounded-box border border-base-300 p-6 text-center">Groups have not been announced yet.</p>
@else
    <nav aria-label="Choose a group" class="my-6 flex flex-wrap justify-center gap-2">
        @foreach ($tableData['groups'] as $group)
            <a class="btn btn-outline" href="#group-{{ $group['id'] }}">{{ $group['name'] }}</a>
        @endforeach
    </nav>
    <p class="mb-6 text-center text-sm text-base-content/80">Positions use points, then net run rate (NRR). Equal records share a position. Qualification is confirmed separately by the committee.</p>
    <div class="space-y-8">
        @foreach ($tableData['groups'] as $group)
            <section id="group-{{ $group['id'] }}" aria-labelledby="group-{{ $group['id'] }}-heading" class="min-w-0 scroll-mt-24 rounded-box border border-base-300 p-4 sm:p-6">
                <h3 id="group-{{ $group['id'] }}-heading" class="break-words text-center font-heading text-3xl">{{ $group['name'] }}</h3>
                @if ($group['teams'] === [])
                    <p class="mt-4 text-center">Teams have not been assigned to this group yet.</p>
                @else
                    <div role="region" aria-label="{{ $group['name'] }} standings, scroll for more columns" tabindex="0" class="mt-4 overflow-x-auto focus-visible:outline-2 focus-visible:outline-primary">
                        <table class="table text-center">
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
                                            {{ $team['name'] }}
                                            @if ($team['qualified'])
                                                <span class="badge badge-outline mt-1 h-auto whitespace-normal py-1 text-xs font-normal">Qualification confirmed</span>
                                            @endif
                                        </th>
                                        <td>{{ $team['played'] }}</td><td>{{ $team['wins'] }}</td><td>{{ $team['losses'] }}</td>
                                        <td>{{ $team['ties'] }}</td><td>{{ $team['no_results'] }}</td>
                                        <td class="font-bold">{{ $team['points'] }}</td><td>{{ $team['nrr'] ?? 'Not available' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
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
            </section>
        @endforeach
    </div>

    <section aria-labelledby="runner-guidance-heading" class="mt-8 rounded-box border border-base-300 p-4 sm:p-6">
        <h3 id="runner-guidance-heading" class="text-center font-heading text-3xl">Runner-up comparison</h3>
        <p class="mx-auto mt-3 max-w-[56ch] text-center text-base-content/80">This comparison uses points, then NRR. It does not confirm qualification. Teams sharing the second position, including joint leaders, are included.</p>
        @if ($tableData['unequal_groups'])
            <p class="mt-3 text-center font-semibold">Groups have different team counts. Cross-group comparisons need committee review.</p>
        @endif
        @if ($tableData['runner_up_guidance'] === [])
            <p class="mt-4 text-center">No runner-up comparison is available yet.</p>
        @else
            <div role="region" aria-label="Runner-up comparison, scroll for more columns" tabindex="0" class="mt-4 overflow-x-auto focus-visible:outline-2 focus-visible:outline-primary">
                <table class="table text-center">
                    <caption class="sr-only">Runner-up candidates, not confirmed qualification</caption>
                    <thead><tr><th scope="col">Position</th><th scope="col">Team</th><th scope="col">Group</th><th scope="col">Played</th><th scope="col">Points</th><th scope="col">NRR</th><th scope="col">Comparison</th></tr></thead>
                    <tbody>
                        @foreach ($tableData['runner_up_guidance'] as $team)
                            <tr>
                                <td>{{ $team['rank'] }}</td><th scope="row" class="min-w-40 max-w-72 break-words">{{ $team['name'] }}</th>
                                <td class="break-words">{{ $team['group_name'] }}</td><td>{{ $team['played'] }}</td><td>{{ $team['points'] }}</td>
                                <td>{{ $team['nrr'] ?? 'Not available' }}</td><td>{{ $team['provisional'] ? 'Provisional' : 'Group results complete' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-center text-sm text-base-content/80">Incomplete results, tied positions, unavailable NRR or unequal group sizes make the comparison provisional. Abandoned and no-result matches do not contribute to NRR.</p>
        @endif
    </section>
@endif
