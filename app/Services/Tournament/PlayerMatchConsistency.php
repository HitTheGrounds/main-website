<?php

namespace App\Services\Tournament;

use App\Models\TournamentMatch;
use Illuminate\Validation\ValidationException;

/** Check the effective official player set, not just the pending changes. */
class PlayerMatchConsistency
{
    public function __construct(private PlayerStatsValidator $validator) {}

    public function assertConsistent(TournamentMatch $match, iterable $performances): void
    {
        $rows = collect($performances);
        foreach ($rows as $row) {
            if (! in_array($row->team_id, [$match->team1_id, $match->team2_id], true)) {
                $this->reject('A player performance references a team outside this match.');
            }
            $this->validator->validate($row->only([...PlayerStatsValidator::METRICS, 'batting_status']));
        }
        foreach (['team1' => 'team2', 'team2' => 'team1'] as $own => $opponent) {
            $players = $rows->where('team_id', $match->{$own.'_id'});
            $runs = $players->sum(fn ($row) => max($row->runs_scored ?? 0, 4 * ($row->fours ?? 0) + 6 * ($row->sixes ?? 0)));
            if ($match->{$own.'_runs'} !== null && $runs > $match->{$own.'_runs'}) {
                $this->reject('Known player runs exceed the official '.$own.' innings total. Correct the player figures or team result first.');
            }
            $deliveries = $match->{$opponent.'_deliveries'} ?? $match->overs_per_innings * $match->balls_per_over;
            $bowling = $players->sum(fn ($row) => max($row->bowling_balls ?? 0, $row->dot_balls ?? 0));
            if ($bowling > $deliveries) {
                $this->reject('Known player bowling deliveries exceed the opposing innings allocation or recorded deliveries.');
            }
            $wickets = $match->{$opponent.'_wickets'} ?? $match->max_wickets;
            if ($players->sum('wickets_taken') > $wickets) {
                $this->reject('Known player wickets exceed the opposing innings wickets.');
            }
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['player' => $message]);
    }
}
