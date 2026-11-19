<?php

namespace App\Services\Tournament;

use App\Models\TournamentMatch;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Read-only invariants; authorized writers must call under the common year lock. */
class KnockoutIntegrity
{
    /**
     * Ignore only the current match ID resolved by the authorized edit service,
     * never an unchecked browser exclusion. Eligibility is checked separately.
     */
    public function assertFixture(int $year, string $stage, int $position, int $team1Id, int $team2Id, iterable $matches, ?int $currentMatchId = null): void
    {
        Validator::make(compact('year', 'stage', 'position', 'team1Id', 'team2Id', 'currentMatchId'), [
            'year' => ['integer', 'between:1,9999'], 'stage' => ['required', Rule::in(['QF', 'SF', 'F'])],
            'position' => ['integer', 'min:1', 'max:'.(int) config('tournament.knockout_slots.'.$stage, 0)],
            'team1Id' => ['integer', 'min:1'], 'team2Id' => ['integer', 'min:1', 'different:team1Id'],
            'currentMatchId' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        foreach ($matches as $match) {
            if ($match->tournament_year !== $year || $match->stage !== $stage
                || ($currentMatchId !== null && $match->id === $currentMatchId)) {
                continue;
            }
            if ($match->bracket_position === $position) {
                $this->reject('This knockout position is already occupied. Reload and select an available position.');
            }
            // Every existing fixture reserves its teams, including unfinished
            // fixtures. Check both orientations; never count only team1/team2.
            if (in_array($team1Id, [$match->team1_id, $match->team2_id], true)
                || in_array($team2Id, [$match->team1_id, $match->team2_id], true)) {
                $this->reject('A selected team already has a fixture in this knockout round.');
            }
        }
    }

    /** Completed proposals only. Incomplete result drafts remain permitted. */
    public function assertFinishedResult(TournamentMatch $match, array $result): void
    {
        if (! in_array($match->stage, ['QF', 'SF', 'F'], true)) {
            return;
        }
        if (($result['result_type'] ?? null) !== 'win' || $match->team1_id === $match->team2_id
            || ! isset($result['winner_id']) || ! in_array($result['winner_id'], [$match->team1_id, $match->team2_id], true)) {
            $this->reject('A finished knockout must have one of its two participating teams as the official winner.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['tournament' => $message]);
    }
}
