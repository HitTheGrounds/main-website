<?php

namespace App\Services\Tournament;

use App\Models\GroupTeam;
use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\Validator;

/** Server maintenance only. Normal scorer writes use the publication services. */
class TournamentSummaryRebuildService
{
    public function __construct(
        private TournamentWriteService $writes,
        private GroupStandingsCalculator $standings,
        private PlayerTotalsService $players,
    ) {}

    public function rebuild(int $year): array
    {
        abort_unless(app()->runningInConsole(), 403);
        Validator::make(['year' => $year], ['year' => ['integer', 'between:1,9999']])->validate();

        return $this->writes->run($year, function () use ($year) {
            // Skip tables we do not read/write; retain the shared table order.
            // No registration/team/member or official source record is edited.
            $groups = TournamentGroup::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $matches = TournamentMatch::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $assignments = GroupTeam::where('tournament_year', $year)->orderBy('id')->lockForUpdate()->get();
            $sources = PlayerMatchStat::where('record_state', 'published')
                ->whereHas('tournamentMatch', fn ($query) => $query->where('tournament_year', $year))
                ->orderBy('id')->lockForUpdate()->get(['id', 'team_member_id']);
            // Include existing summaries so stale rows with no remaining eligible
            // source are removed by the same player calculator, not a truncate.
            $memberIds = $sources->pluck('team_member_id')
                ->merge(PlayerTournamentStat::where('tournament_year', $year)->pluck('team_member_id'))
                ->unique()->values()->all();
            $changed = 0;
            foreach ($groups as $group) {
                $rows = $assignments->where('group_id', $group->id);
                $calculated = $this->standings->calculate($group, $rows, $matches->where('group_id', $group->id));
                $groupChanged = false;
                foreach ($rows as $row) {
                    // The calculator returns only derived standings columns.
                    // Committee history and qualified flags are left untouched.
                    foreach ($calculated['rows'][$row->team_id] as $field => $value) {
                        $row->$field = $value;
                    }
                    if ($row->isDirty()) {
                        $row->version++;
                        $row->save();
                        $changed++;
                        $groupChanged = true;
                    }
                }
                if ($groupChanged) {
                    $group->version++;
                    $group->save();
                }
            }
            // Final table in the lock order; one outer transaction covers both
            // calculations; any failure restores the pre-rebuild state.
            $this->players->recalculate($year, $memberIds);

            return ['groups' => $groups->count(), 'standings_updated' => $changed, 'players_checked' => count($memberIds)];
        });
    }
}
