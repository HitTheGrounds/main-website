<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\GroupTeam;
use App\Models\PlayerMatchStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Private, allowlisted scorer read model. All writes use PlayerStatsService. */
class PlayerEntryService
{
    public function __construct(private TournamentWriteService $writes) {}

    public function read(int $year, int $matchId): array
    {
        abort_unless(config('tournament.enabled') === true, 404);
        abort_unless(User::whereKey(CompanyAuth::user()?->id)->where('is_scorer', true)->exists(), 403);

        // A coherent feature snapshot, without consuming the mutation budget.
        return $this->writes->run($year, function () use ($year, $matchId) {
            abort_unless(User::whereKey(CompanyAuth::user()?->id)->sharedLock()->first()?->is_scorer, 403);
            $match = TournamentMatch::whereKey($matchId)->where('tournament_year', $year)->whereIn('stage', app(PlayoffFeature::class)->stages($year))
                ->first(['id', 'stage', 'group_id', 'team1_id', 'team2_id', 'status', 'result_type', 'result_published_at']);
            if (! $match) {
                throw ValidationException::withMessages(['player' => 'Select a match in this tournament year.']);
            }
            $ids = [$match->team1_id, $match->team2_id];
            $teams = Team::whereIn('id', $ids)->where('approved', true)->orderBy('id')->get(['id', 'team_name']);
            $query = GroupTeam::where('tournament_year', $year)->whereIn('team_id', $ids);
            if ($match->stage === 'G') {
                $query->where('group_id', $match->group_id);
            }
            $assigned = $query->count();
            if ($teams->count() !== 2 || $assigned !== 2) {
                throw ValidationException::withMessages(['player' => 'Both teams must still be approved and assigned to this group.']);
            }
            $members = TeamMember::whereIn('team_id', $ids)->orderBy('id')->get(['id', 'team_id', 'name']);
            $fields = ['id', 'team_member_id', 'team_id', 'record_state', 'pending_action', 'version',
                ...PlayerStatsValidator::METRICS, 'batting_status'];
            $rows = PlayerMatchStat::where('match_id', $matchId)->whereIn('team_id', $ids)->orderBy('id')
                ->get($fields)->map(fn ($row) => $row->only($fields));

            return ['match' => $match->only(['id', 'status', 'result_type']),
                'eligible' => $match->status === 'finished' && $match->result_published_at !== null && in_array($match->result_type, ['win', 'tie'], true),
                'teams' => $teams->toArray(), 'players' => $members->toArray(), 'rows' => $rows->toArray()];
        });
    }
}
