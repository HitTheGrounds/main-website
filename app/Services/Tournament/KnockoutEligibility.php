<?php

namespace App\Services\Tournament;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Read-only rules over trusted models, like GroupStandingsCalculator.
 * No queries, writes or authorization grants. An authorized caller must load a
 * coherent year snapshot; scheduling must recheck under the common write lock.
 */
class KnockoutEligibility
{
    private const PREVIOUS = ['QF' => null, 'SF' => 'QF', 'F' => 'SF'];

    public function candidates(int $year, string $stage, iterable $teams, iterable $assignments, iterable $matches): array
    {
        $this->validateRound($year, $stage);
        $assigned = collect($assignments)->where('tournament_year', $year)->keyBy('team_id');
        $sources = $this->sources($year, $stage, $matches);
        $candidates = [];
        foreach ($teams as $team) {
            $assignment = $assigned->get($team->id);
            if (! $team->approved || ! $assignment) {
                continue;
            }
            if ($stage === 'QF' ? ! $assignment->qualified : ! isset($sources[$team->id])) {
                continue;
            }
            $candidates[$team->id] = ['id' => $team->id, 'name' => $team->team_name,
                'source_match_id' => $stage === 'QF' ? null : $sources[$team->id]];
        }
        // Stable presentation only, never a seed, pairing or tie-break.
        ksort($candidates);

        return array_values($candidates);
    }

    /** Preserve the scorer's participant order; never infer opponents or slots. */
    public function pair(int $year, string $stage, int $team1Id, int $team2Id, iterable $teams, iterable $assignments, iterable $matches): array
    {
        Validator::make(compact('team1Id', 'team2Id'), [
            'team1Id' => ['integer', 'min:1'], 'team2Id' => ['integer', 'min:1', 'different:team1Id'],
        ])->validate();
        $candidates = collect($this->candidates($year, $stage, $teams, $assignments, $matches))->keyBy('id');
        if (! $candidates->has($team1Id) || ! $candidates->has($team2Id)) {
            $this->reject('Select two approved teams with confirmed qualification or a published win in the preceding round of this year.');
        }

        return ['stage' => $stage, 'team1' => $candidates->get($team1Id), 'team2' => $candidates->get($team2Id)];
    }

    /** Actual connections for filled positions; no feeder assumptions for blanks. */
    public function round(int $year, string $stage, iterable $teams, iterable $assignments, iterable $matches): array
    {
        $this->validateRound($year, $stage);
        $teams = collect($teams);
        $assignments = collect($assignments);
        $matches = collect($matches);
        $candidates = collect($this->candidates($year, $stage, $teams, $assignments, $matches))->keyBy('id');
        $names = collect($teams)->keyBy('id');
        $sources = $this->sources($year, $stage, $matches);
        $existing = $matches->where('tournament_year', $year)->where('stage', $stage)->groupBy('bracket_position');
        $positions = [];
        for ($slot = 1; $slot <= (int) config('tournament.knockout_slots.'.$stage); $slot++) {
            $fixtures = $existing->get($slot, collect());
            if ($fixtures->count() > 1) {
                $this->reject('A knockout position contains conflicting fixtures.');
            }
            $match = $fixtures->first();
            if (! $match) {
                $positions[] = ['position' => $slot, 'match_id' => null, 'label' => 'To be confirmed', 'team1' => null, 'team2' => null];

                continue;
            }
            $sides = [];
            foreach (['team1', 'team2'] as $side) {
                $id = $match->{$side.'_id'};
                $eligible = $candidates->get($id);
                $sides[$side] = ['id' => $id, 'name' => $names->get($id)?->team_name,
                    'eligible' => $eligible !== null, 'source_match_id' => $sources[$id] ?? null];
            }
            $positions[] = ['position' => $slot, 'match_id' => $match->id, 'label' => null, ...$sides];
        }

        return $positions;
    }

    /** Historical edges remain truthful even if current approval changes. */
    private function sources(int $year, string $stage, iterable $matches): array
    {
        $sources = [];
        if (self::PREVIOUS[$stage] !== null) {
            foreach ($matches as $match) {
                if ($match->tournament_year !== $year || $match->stage !== self::PREVIOUS[$stage]
                    || $match->status !== 'finished' || $match->result_published_at === null || $match->result_type !== 'win') {
                    continue;
                }
                if (! $match->winner_id || $match->team1_id === $match->team2_id
                    || ! in_array($match->winner_id, [$match->team1_id, $match->team2_id], true)) {
                    $this->reject('A published preceding-round result has an invalid winner.');
                }
                if (isset($sources[$match->winner_id])) {
                    $this->reject('A team has more than one published win in the preceding round. Resolve the conflicting fixtures.');
                }
                $sources[$match->winner_id] = $match->id;
            }
        }

        return $sources;
    }

    private function validateRound(int $year, string $stage): void
    {
        Validator::make(compact('year', 'stage'), [
            'year' => ['integer', 'between:1,9999'], 'stage' => ['required', Rule::in(array_keys(self::PREVIOUS))],
        ])->validate();
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['tournament' => $message]);
    }
}
