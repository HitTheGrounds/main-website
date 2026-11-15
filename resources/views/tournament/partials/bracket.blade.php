@php
    $stages = ['G' => 'Group stage', 'QF' => 'Quarter-final', 'SF' => 'Semi-final', 'F' => 'Final'];
    $statuses = ['upcoming' => 'Upcoming', 'live' => 'In progress', 'finished' => 'Finished'];
    $sources = [];
    foreach ($bracketData['rounds'] as $stage => $positions) {
        foreach ($positions as $position) {
            if ($position['match'] !== null) {
                $sources[$position['match']['id']] = $stages[$stage].' '.$position['position'];
            }
        }
    }
@endphp
<nav aria-label="Choose a knockout round" class="my-6 flex flex-wrap justify-center gap-2">
    @foreach ($bracketData['rounds'] as $stage => $positions)
        <a href="#round-{{ $stage }}" class="btn btn-outline">{{ $stages[$stage] }}</a>
    @endforeach
</nav>
<p class="mb-6 text-center text-sm text-base-content/80">Matchups are confirmed by the scorers. Connections below show published wins from the preceding round.</p>
<div class="space-y-8">
    @foreach ($bracketData['rounds'] as $stage => $positions)
        <section id="round-{{ $stage }}" aria-labelledby="round-{{ $stage }}-heading" class="min-w-0 scroll-mt-24">
            <h3 id="round-{{ $stage }}-heading" class="mb-4 text-center font-heading text-3xl">{{ $stages[$stage] }}</h3>
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach ($positions as $position)
                    <div class="min-w-0">
                        @if ($position['match'] !== null)
                            @include('tournament.partials.match-card', ['match' => $position['match']])
                            @if ($stage !== 'QF')
                                <ul aria-label="{{ $stages[$stage] }} {{ $position['position'] }} connections" class="mt-3 space-y-2 text-center text-sm">
                                    @foreach (['team1', 'team2'] as $side)
                                        @php $team = $position[$side]; @endphp
                                        <li class="break-words">
                                            {{ $team['name'] }}:
                                            @if ($team['source_match_id'] !== null && isset($sources[$team['source_match_id']]))
                                                <a class="link underline-offset-4" href="#match-{{ $team['source_match_id'] }}-heading">Winner of {{ $sources[$team['source_match_id']] }}</a>
                                            @else
                                                Previous-round result not confirmed
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @else
                            <div class="rounded-box border border-base-300 p-6 text-center">
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
