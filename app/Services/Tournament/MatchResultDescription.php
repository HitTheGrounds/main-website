<?php

namespace App\Services\Tournament;

use App\Models\TournamentMatch;
use Illuminate\Validation\ValidationException;

class MatchResultDescription
{
    /** Return plain text from a validated result. Views must escape team names. */
    public function describe(TournamentMatch $match, array $result, string $team1, string $team2): string
    {
        $type = $result['result_type'];
        if ($type !== 'win') {
            return match ($type) {
                'tie' => 'Match tied.', 'no_result' => 'No result.', 'abandoned' => 'Match abandoned.',
                default => $this->reject(),
            };
        }
        $winner = $result['winner_id'];
        $name = match ($winner) {
            $match->team1_id => $team1, $match->team2_id => $team2, default => $this->reject(),
        };
        $method = $result['result_method'];
        if ($method !== 'normal') {
            return $name.match ($method) {
                'super_over' => ' won after a Super Over.', 'coin_toss' => ' won by coin toss.',
                'revised_target' => ' won by the official revised-target decision.',
                'official_decision' => ' won by official decision.', default => $this->reject(),
            };
        }
        if ($result['batting_first_id'] === null) {
            return $name.' won.';
        }
        $own = $winner === $match->team1_id ? 'team1' : 'team2';
        $other = $own === 'team1' ? 'team2' : 'team1';
        if ($result[$own.'_runs'] <= $result[$other.'_runs']) {
            $this->reject();
        }
        if ($winner === $result['batting_first_id']) {
            $margin = $result[$own.'_runs'] - $result[$other.'_runs'];

            return $name.' won by '.$margin.($margin === 1 ? ' run.' : ' runs.');
        }
        $margin = $match->max_wickets - $result[$own.'_wickets'];
        if ($margin <= 0) {
            $this->reject();
        }

        return $name.' won by '.$margin.($margin === 1 ? ' wicket.' : ' wickets.');
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['tournament' => 'The result cannot be described consistently.']);
    }
}
