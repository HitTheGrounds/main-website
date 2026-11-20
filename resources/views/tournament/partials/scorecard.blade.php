<p class="tc-scorecard-result">{{ $match['result']['description'] }}</p>
<fieldset class="tc-innings">
    <legend class="sr-only">Batting team</legend>
    @foreach ($sides as $side)
        <label><input type="radio" name="innings" value="{{ $side }}" @checked($loop->first)>
            <span>{{ $match[$side]['name'] }}</span>
        </label>
    @endforeach
</fieldset>
@foreach ($sides as $side)
    @php
        $other = $side === 'team1' ? 'team2' : 'team1';
        $batting = $players->get($match[$side]['id'], collect());
        $bowlers = $players->get($match[$other]['id'], collect())->filter(fn ($p) =>
            $p->bowling_balls > 0 || $p->runs_conceded > 0 || $p->wickets_taken > 0 || $p->dot_balls > 0);
        $total = $match['result'][$side];
    @endphp
    <section data-innings="{{ $side }}" @if (!$loop->first) hidden @endif aria-label="{{ $match[$side]['name'] }} innings">
        <h3>Batting</h3>
        <div class="tc-score-table"><table aria-label="{{ $match[$side]['name'] }} batting">
            <thead><tr><th>Batter</th><th>R</th><th>B</th><th>4s</th><th>6s</th><th>SR</th></tr></thead>
            <tbody>
            @forelse ($batting->filter(fn ($p) => $p->batting_status !== null || $p->runs_scored !== null || $p->balls_faced !== null || $p->fours !== null || $p->sixes !== null) as $p)
                <tr><th scope="row">{{ $p->player_name }}
                    <small>{{ ucfirst(str_replace('_', ' ', $p->batting_status ?? '')) }}</small></th>
                    <td>{{ $p->runs_scored ?? '—' }}</td><td>{{ $p->balls_faced ?? '—' }}</td>
                    <td>{{ $p->fours ?? '—' }}</td><td>{{ $p->sixes ?? '—' }}</td>
                    <td>{{ $p->balls_faced > 0 && $p->runs_scored !== null ? number_format(100 * $p->runs_scored / $p->balls_faced, 2) : '—' }}</td></tr>
            @empty
                <tr><td colspan="6">No batting figures published.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <p class="tc-innings-total"><span>Total</span> <strong>{{ $total['runs'] ?? '—' }}@if ($total['wickets'] !== null)/{{ $total['wickets'] }}@endif</strong>
            @if ($total['overs'] !== null) <small>({{ $total['overs'] }}.{{ $total['balls'] }} overs)</small> @endif</p>
        <h3>Bowling <small>{{ $match[$other]['name'] }}</small></h3>
        <div class="tc-score-table"><table aria-label="{{ $match[$other]['name'] }} bowling">
            <thead><tr><th>Bowler</th><th>O</th><th>Dots</th><th>R</th><th>W</th><th>Econ</th></tr></thead>
            <tbody>
            @forelse ($bowlers as $p)
                <tr><th scope="row">{{ $p->player_name }}</th>
                    <td>{{ $p->bowling_balls === null ? '—' : intdiv($p->bowling_balls, $match['balls_per_over']).'.'.($p->bowling_balls % $match['balls_per_over']) }}</td>
                    <td>{{ $p->dot_balls ?? '—' }}</td><td>{{ $p->runs_conceded ?? '—' }}</td><td>{{ $p->wickets_taken ?? '—' }}</td>
                    <td>{{ $p->bowling_balls > 0 && $p->runs_conceded !== null ? number_format($p->runs_conceded * $match['balls_per_over'] / $p->bowling_balls, 2) : '—' }}</td></tr>
            @empty
                <tr><td colspan="6">No bowling figures published.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
@endforeach
