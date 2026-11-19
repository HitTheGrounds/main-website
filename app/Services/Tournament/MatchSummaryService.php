<?php

namespace App\Services\Tournament;

use App\Models\PlayerMatchStat;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;

/** Called only inside the authorized year transaction, after participant locks. */
class MatchSummaryService
{
    public function __construct(private GroupStandingsCalculator $standings, private PlayerTotalsService $players, private PlayerMatchConsistency $consistency) {}

    public function recalculate(TournamentMatch $match, ?TournamentGroup $group, iterable $matches, iterable $assignments): void
    {
        $publishedPlayers = PlayerMatchStat::where('match_id', $match->id)->where('record_state', 'published')
            ->orderBy('id')->lockForUpdate()->get();
        if ($match->status === 'finished' && in_array($match->result_type, ['win', 'tie'], true)) {
            // Covers both result corrections and restoring a reopened result.
            $this->consistency->assertConsistent($match, $publishedPlayers);
        }
        if ($group !== null) {
            $calculated = $this->standings->calculate($group, $assignments, $matches);
            foreach ($assignments as $assignment) {
                foreach ($calculated['rows'][$assignment->team_id] as $field => $value) {
                    $assignment->$field = $value;
                }
                if ($assignment->isDirty()) {
                    $assignment->version++;
                    $assignment->save();
                }
            }
            $group->version++;
            $group->save();
        }
        // Preserve official performances and unrelated drafts. Eligibility is
        // determined from the newly saved match, never from pending player data.
        $ids = $publishedPlayers->pluck('team_member_id')->unique()->values()->all();
        $this->players->recalculate($match->tournament_year, $ids);
    }
}
