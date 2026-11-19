<?php

namespace App\Services\Tournament;

use App\Models\GroupTeam;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupStandingsCalculator
{
    /**
     * Read-only derived rows keyed by team ID, plus explicit NRR exclusions.
     * The authorized publication service must load a consistent locked source
     * set and persist only rows, preserving adjustment history/qualification.
     *
     * @param  iterable<GroupTeam>  $assignments
     * @param  iterable<TournamentMatch>  $matches
     */
    public function calculate(TournamentGroup $group, iterable $assignments, iterable $matches): array
    {
        Validator::make($group->only(['id', 'tournament_year']), [
            'id' => ['required', 'integer', 'min:1'], 'tournament_year' => ['required', 'integer', 'between:1,9999'],
        ])->validate();
        $points = Validator::make(config('tournament.points'), array_fill_keys(['win', 'loss', 'tie', 'no_result'],
            ['required', 'integer', 'between:0,2147483647']))->validate();
        $rows = $overs = [];
        foreach ($assignments as $assignment) {
            if ($assignment->group_id !== $group->id || $assignment->tournament_year !== $group->tournament_year) {
                continue;
            }
            $teamId = $assignment->team_id;
            if (! $teamId || isset($rows[$teamId])) {
                $this->reject('The group has an invalid or duplicate team assignment.');
            }
            $adjustment = $this->adjustments($assignment->point_adjustments ?? []);
            $rows[$teamId] = ['played' => 0, 'wins' => 0, 'losses' => 0, 'ties' => 0, 'no_results' => 0,
                'base_points' => 0, 'points' => $adjustment, 'runs_for' => 0, 'runs_against' => 0];
            $overs[$teamId] = ['faced' => BigRational::zero(), 'bowled' => BigRational::zero()];
        }
        $seen = $exclusions = [];
        foreach ($matches as $match) {
            if ($match->stage !== 'G' || $match->group_id !== $group->id || $match->tournament_year !== $group->tournament_year
                || $match->status !== 'finished' || $match->result_published_at === null) {
                continue;
            }
            if (! $match->id || isset($seen[$match->id])) {
                $this->reject('A published match is missing its identity or was supplied more than once.');
            }
            $seen[$match->id] = true;
            $a = $match->team1_id;
            $b = $match->team2_id;
            if ($a === $b || ! isset($rows[$a], $rows[$b])) {
                $this->reject('A published match has participants outside this group.');
            }
            $type = $match->result_type;
            if (! in_array($type, ['win', 'tie', 'no_result', 'abandoned'], true)
                || ($type === 'win' && ! in_array($match->winner_id, [$a, $b], true))
                || ($type !== 'win' && $match->winner_id !== null)
                || ($type === 'tie' && $match->result_method !== 'normal')
                || (in_array($type, ['no_result', 'abandoned'], true) && $match->result_method !== null)) {
                $this->reject('A published group result has an invalid outcome or winner.');
            }
            foreach ([$a, $b] as $teamId) {
                $outcome = $type === 'win' ? ($match->winner_id === $teamId ? 'win' : 'loss')
                    : ($type === 'tie' ? 'tie' : 'no_result');
                $counter = ['win' => 'wins', 'loss' => 'losses', 'tie' => 'ties', 'no_result' => 'no_results'][$outcome];
                $rows[$teamId]['played']++;
                $rows[$teamId][$counter]++;
                $rows[$teamId]['base_points'] += (int) $points[$outcome];
                $rows[$teamId]['points'] += (int) $points[$outcome];
            }
            if (in_array($type, ['no_result', 'abandoned'], true)) {
                $exclusions[$match->id] = 'Abandoned/no-result matches are excluded from NRR.';

                continue;
            }
            $innings = $this->innings($match);
            if (isset($innings['excluded'])) {
                $exclusions[$match->id] = $innings['excluded'];

                continue;
            }
            foreach ([[$a, 'team1', 'team2'], [$b, 'team2', 'team1']] as [$teamId, $own, $opponent]) {
                $rows[$teamId]['runs_for'] += $innings[$own]['runs'];
                $rows[$teamId]['runs_against'] += $innings[$opponent]['runs'];
                $overs[$teamId]['faced'] = $overs[$teamId]['faced']->plus($innings[$own]['overs'])->simplified();
                $overs[$teamId]['bowled'] = $overs[$teamId]['bowled']->plus($innings[$opponent]['overs'])->simplified();
            }
        }
        foreach ($rows as $teamId => &$row) {
            foreach ($row as $value) {
                if ($value < -2147483648 || $value > 2147483647) {
                    $this->reject('The calculated group totals exceed their supported storage range.');
                }
            }
            $faced = $overs[$teamId]['faced'];
            $bowled = $overs[$teamId]['bowled'];
            $facedDecimal = $faced->toScale(8, RoundingMode::HALF_UP);
            $bowledDecimal = $bowled->toScale(8, RoundingMode::HALF_UP);
            if ($facedDecimal->isGreaterThanOrEqualTo('10000000000') || $bowledDecimal->isGreaterThanOrEqualTo('10000000000')) {
                $this->reject('The calculated NRR denominators exceed their supported storage range.');
            }
            $row['nrr_overs_faced'] = (string) $facedDecimal;
            $row['nrr_overs_bowled'] = (string) $bowledDecimal;
            $row['nrr'] = $faced->isZero() || $bowled->isZero() ? null
                : (string) BigRational::of($row['runs_for'])->dividedBy($faced)
                    ->minus(BigRational::of($row['runs_against'])->dividedBy($bowled))->toScale(6, RoundingMode::HALF_UP);
        }
        unset($row);
        ksort($rows);
        ksort($exclusions);

        return ['rows' => $rows, 'nrr_exclusions' => $exclusions];
    }

    private function adjustments(mixed $entries): int
    {
        if (! is_array($entries) || ! array_is_list($entries)) {
            $this->reject('The official point-adjustment history is invalid.');
        }
        $total = 0;
        foreach ($entries as $entry) {
            if (! is_array($entry) || ! isset($entry['points'], $entry['reason']) || ! is_int($entry['points'])
                || ! is_string($entry['reason']) || trim($entry['reason']) === '') {
                $this->reject('Every official point adjustment needs integer points and a reason.');
            }
            $total += $entry['points'];
            if ($total < -2147483648 || $total > 2147483647) {
                $this->reject('The official point adjustments exceed their supported storage range.');
            }
        }

        return $total;
    }

    private function innings(TournamentMatch $match): array
    {
        Validator::make($match->only(['balls_per_over', 'overs_per_innings', 'max_wickets', 'result_method']), [
            'balls_per_over' => ['required', Rule::in([4, 6])], 'overs_per_innings' => ['required', Rule::in([5])],
            'max_wickets' => ['required', Rule::in([7])],
            'result_method' => ['required', Rule::in(['normal', 'super_over', 'coin_toss', 'revised_target', 'official_decision'])],
        ])->validate();
        $adjusted = $match->result_method === 'revised_target';
        $inputs = $match->nrr_inputs;
        if ($adjusted) {
            if (! is_array($inputs) || ! isset($inputs['included'], $inputs['reason']) || ! is_bool($inputs['included'])
                || ! is_string($inputs['reason']) || trim($inputs['reason']) === '') {
                $this->reject('A revised-target result needs an explicit official NRR inclusion/exclusion decision.');
            }
            if (! $inputs['included']) {
                if (isset($inputs['team1']) || isset($inputs['team2'])) {
                    $this->reject('Excluded NRR must not contain adjusted figures.');
                }

                return ['excluded' => $inputs['reason']];
            }
        }
        $result = [];
        foreach (['team1', 'team2'] as $team) {
            $runs = $adjusted ? ($inputs[$team]['runs'] ?? null) : $match->{$team.'_runs'};
            $count = $adjusted ? ($inputs[$team]['deliveries'] ?? null) : $match->{$team.'_deliveries'};
            $wickets = $match->{$team.'_wickets'};
            if ($runs === null || $count === null || (! $adjusted && $wickets === null)) {
                if ($adjusted || in_array($match->result_method, ['normal', 'super_over'], true)) {
                    $this->reject('A published result is missing required official NRR figures.');
                }

                return ['excluded' => 'Official innings figures are incomplete.'];
            }
            if (! is_int($runs) || $runs < 0 || $runs > 2147483647 || ! is_int($count) || $count < 0
                || $count > $match->overs_per_innings * $match->balls_per_over
                || (! $adjusted && ($wickets < 0 || $wickets > $match->max_wickets))) {
                $this->reject('A published result has invalid official NRR figures.');
            }
            // Committee-adjusted denominators are already official; do not apply
            // the actual innings' all-out rule to them a second time.
            $result[$team] = ['runs' => $runs, 'overs' => ! $adjusted && $wickets === $match->max_wickets
                ? BigRational::of($match->overs_per_innings) : BigRational::of($count)->dividedBy($match->balls_per_over)];
        }
        if ($match->result_method === 'normal'
            && (($match->result_type === 'tie' && $match->team1_runs !== $match->team2_runs)
                || ($match->result_type === 'win' && ($match->winner_id === $match->team1_id
                    ? $match->team1_runs <= $match->team2_runs : $match->team2_runs <= $match->team1_runs)))) {
            $this->reject('A published normal-play result disagrees with its official scores.');
        }
        if ($match->result_method === 'super_over' && $match->team1_runs !== $match->team2_runs) {
            $this->reject('A published Super Over decision must retain tied normal scores.');
        }

        return $result;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['tournament' => $message]);
    }
}
