<?php

namespace App\Services\Tournament;

use App\Models\PlayerMatchStat;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;

/** Called only inside the authorized year transaction, after participant locks. */
class MatchSummaryService
{
    public function __construct(private GroupStandingsCalculator $standings, private PlayerTotalsService $players) {}

    public function recalculate(TournamentMatch $match, ?TournamentGroup $group, iterable $matches, iterable $assignments): void
    {
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
        $ids = PlayerMatchStat::where('match_id', $match->id)->where('record_state', 'published')
            ->orderBy('id')->lockForUpdate()->get(['id', 'team_member_id'])
            ->pluck('team_member_id')->unique()->values()->all();
        $this->players->recalculate($match->tournament_year, $ids);
    }
}
