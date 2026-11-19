{{-- Scorer dashboard match card. Scores come from the official columns only and
     appear once the result is published; draft figures never reach this view.
     Styles: resources/css/scorer.css (sd-card-*). --}}
@php
    $statusLabels = ['upcoming' => 'Upcoming', 'live' => 'In progress', 'finished' => 'Finished'];
    $stageLabels = ['G' => 'Group', 'QF' => 'Quarter-final', 'SF' => 'Semi-final', 'F' => 'Final'];
    $published = $match->status === 'finished' && $match->result_published_at !== null;
    $names = ['team1' => $match->team1->team_name, 'team2' => $match->team2->team_name];
    $description = $published && $match->result_type !== null
        ? rescue(fn () => app(\App\Services\Tournament\MatchResultDescription::class)->describe($match,
            $match->only(['result_type', 'result_method', 'winner_id', 'batting_first_id', 'team1_runs', 'team1_wickets', 'team2_runs', 'team2_wickets']),
            $names['team1'], $names['team2']), null, false)
        : null;
    $scheduled = $match->scheduled_at?->setTimezone(config('tournament.timezone'));
@endphp
<article wire:key="match-{{ $match->id }}" aria-labelledby="sd-match-{{ $match->id }}" @class(['sd-card', 'sd-card-'.$match->status])>
    <header class="sd-card-top">
        <p class="sd-card-context">
            <span>{{ $match->group?->name ?? $stageLabels[$match->stage] ?? $match->stage }}</span>
            @if($match->display_number !== null)<span aria-hidden="true">·</span><span>Match {{ $match->display_number }}</span>@endif
        </p>
        <span class="sd-status sd-status-{{ $match->status }}">
            @if($match->status === 'live')<i class="sd-live-dot" aria-hidden="true"></i>
            @else<x-scorer.icon :name="$match->status === 'finished' ? 'check' : 'clock'" />@endif
            {{ $statusLabels[$match->status] }}
        </span>
    </header>

    <h3 id="sd-match-{{ $match->id }}" class="sr-only">{{ $names['team1'] }} vs {{ $names['team2'] }}</h3>
    <dl class="sd-card-board">
        @foreach(['team1', 'team2'] as $side)
            @php
                $teamId = $match->{$side.'_id'};
                $won = $published && $match->winner_id !== null && (int) $match->winner_id === (int) $teamId;
                $lost = $published && $match->winner_id !== null && ! $won;
                $runs = $match->{$side.'_runs'};
                $wickets = $match->{$side.'_wickets'};
                $balls = $match->{$side.'_deliveries'};
            @endphp
            <div @class(['sd-team', 'sd-team-won' => $won, 'sd-team-lost' => $lost])>
                <dt>
                    <x-tournament.team-badge :name="$names[$side]" />
                    <span class="sd-team-name">{{ $names[$side] }}</span>
                    @if($won)<span class="sr-only">(winner)</span>@endif
                    @if((int) $match->batting_first_id === (int) $teamId && ! $published)<span class="sd-bat-first">Bats first</span>@endif
                </dt>
                <dd>
                    @if($published && $runs !== null)
                        <span class="sd-score">{{ $runs }}@if($wickets !== null)<span class="sd-score-wk">/{{ $wickets }}</span>@endif</span>
                        @if($balls !== null)<span class="sd-overs">{{ intdiv($balls, $match->balls_per_over) }}.{{ $balls % $match->balls_per_over }} ov</span>@endif
                    @elseif($published && in_array($match->result_type, ['win', 'tie'], true))
                        <span class="sd-overs">Not recorded</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>

    @if($description)
        <p @class(['sd-card-result', 'sd-card-result-neutral' => $match->result_type !== 'win'])><x-scorer.icon :name="$match->result_type === 'win' ? 'trophy' : 'ball'" /> {{ $description }}</p>
    @elseif($match->status === 'finished')
        <p class="sd-card-result sd-card-result-pending">Result not published yet.</p>
    @endif

    <ul class="sd-card-meta">
        <li><x-scorer.icon name="calendar" /><span>{{ $scheduled?->format('d M Y, H:i') ?? 'Time not set' }}</span></li>
        <li><x-scorer.icon name="pin" /><span>{{ $match->venue ?? 'Venue not set' }}</span></li>
        <li><x-scorer.icon name="ball" /><span>{{ $match->balls_per_over }} balls per over | {{ $match->overs_per_innings }} overs | {{ $match->max_wickets }} wickets</span></li>
    </ul>

    <footer class="sd-card-actions">
        <a href="{{ route('scorer.matches.result', ['matchId' => $match->id]) }}" class="btn btn-primary btn-sm">
            <x-scorer.icon name="scoreboard" /> Team result
        </a>
        <a href="{{ route('scorer.matches.players', ['matchId' => $match->id]) }}" class="btn btn-outline btn-sm">
            <x-scorer.icon name="users" /> Player figures
        </a>
        <button type="button" wire:click="openMatch({{ $match->id }})" wire:loading.attr="disabled" class="btn btn-ghost btn-sm sd-edit-btn">
            <x-scorer.icon name="pencil" /> Edit schedule
        </button>
    </footer>
</article>
