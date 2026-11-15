<?php

namespace App\Services\Tournament;

use App\Models\PlayerMatchStat;
use App\Models\TournamentMatch;
use Illuminate\Validation\ValidationException;

/** Call inside the authorized year transaction with the current match locked. */
class MatchTransitionGuard
{
    public function assertScheduleStatusChange(TournamentMatch $match, string $status): void
    {
        if ($status === $match->status) {
            return;
        }

        if ($match->status === 'finished' || $match->structure_locked_at !== null
            || $match->result_published_at !== null || $match->result_type !== null
            || $match->winner_id !== null || $this->hasPublishedPlayers($match)) {
            // Do not clear results or derived data through a scheduling update.
            // Tasks 6/7 must supply atomic totals and dependency handling first.
            throw ValidationException::withMessages([
                'status' => 'Published or finished results cannot change status through scheduling.',
            ]);
        }
    }

    public function assertResultPublication(TournamentMatch $match): void
    {
        if ($this->hasPublishedPlayers($match)) {
            throw ValidationException::withMessages([
                'tournament' => 'This match has published player figures. Result corrections require the player-total integration first.',
            ]);
        }

        // Conservative temporary gate: task 7 replaces this with checks against
        // actual dependent fixtures. Never infer a bracket from table rankings.
        if (TournamentMatch::where('tournament_year', $match->tournament_year)
            ->whereIn('stage', ['QF', 'SF', 'F'])->exists()) {
            throw ValidationException::withMessages([
                'tournament' => 'Knockout matches depend on this tournament. Result changes require the bracket dependency checks first.',
            ]);
        }
    }

    private function hasPublishedPlayers(TournamentMatch $match): bool
    {
        return $match->player_stats_published_at !== null
            || PlayerMatchStat::where('match_id', $match->id)->where('record_state', 'published')->exists();
    }
}
