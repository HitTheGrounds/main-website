<?php

namespace App\Services\Tournament;

use App\Models\PlayerMatchStat;
use App\Models\TournamentMatch;
use Illuminate\Validation\ValidationException;

/** Call inside the authorized year transaction with the year matches locked. */
class MatchTransitionGuard
{
    public function __construct(
        private KnockoutDependencies $dependencies,
        private KnockoutEligibility $eligibility,
        private KnockoutIntegrity $integrity,
        private MatchResultValidator $results,
    ) {}

    public function assertScheduleStatusChange(TournamentMatch $match, string $status, iterable $matches, iterable $assignments = []): void
    {
        if ($status === $match->status) {
            return;
        }
        if ($match->result_published_at !== null) {
            // Restoring finished uses only validated official columns. It never
            // publishes the separate saved result or player drafts.
            if ($status === 'finished') {
                $this->results->validate($match, $this->results->officialForm($match));
            }
            $proposed = clone $match;
            $proposed->status = $status;
            $this->dependencies->assertChange($match, $proposed, $matches, $assignments);

            return;
        }
        if ($status === 'finished' || $match->status === 'finished' || $match->structure_locked_at !== null
            || $match->result_type !== null || $match->winner_id !== null
            || $match->player_stats_published_at !== null
            || PlayerMatchStat::where('match_id', $match->id)->where('record_state', 'published')->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Publish a valid result before finishing this match. Inconsistent published history must be resolved first.',
            ]);
        }
    }

    public function assertResultPublication(TournamentMatch $before, TournamentMatch $proposed, iterable $matches, iterable $assignments = []): void
    {
        $this->dependencies->assertChange($before, $proposed, $matches, $assignments);
    }

    public function assertParticipants(TournamentMatch $match, iterable $teams, iterable $assignments, iterable $matches): void
    {
        if ($match->stage === 'G') {
            return;
        }
        if ($match->group_id !== null) {
            throw ValidationException::withMessages(['tournament' => 'A knockout match cannot belong to a group.']);
        }
        $this->eligibility->pair($match->tournament_year, $match->stage, $match->team1_id, $match->team2_id, $teams, $assignments, $matches, app(PlayoffFeature::class)->enabled($match->tournament_year));
        if ($match->stage !== 'PO') {
            $this->integrity->assertFixture($match->tournament_year, $match->stage, (int) $match->bracket_position,
                $match->team1_id, $match->team2_id, $matches, $match->id);
        }
    }
}
