<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\ScorerWriteThrottle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlayerStatsContext
{
    public function __construct(private TournamentWriteService $writes, private RosterProtectionService $rosters, private MatchTransitionGuard $transitions) {}

    /**
     * Single-player write context for the draft lifecycle. The callback must
     * validate row identities/versions and touch only feature data. A later
     * match-level publisher must lock all teams/members in order before batching.
     * This helper itself stores no figures or snapshots.
     */
    public function withMember(int $year, int $matchId, int $teamId, int $memberId, callable $write, bool $publication = false): mixed
    {
        ScorerWriteThrottle::hit();
        Validator::make(compact('year', 'matchId', 'teamId', 'memberId'), [
            'year' => ['integer', 'between:1,9999'], 'matchId' => ['integer', 'min:1'],
            'teamId' => ['integer', 'min:1'], 'memberId' => ['integer', 'min:1'],
        ])->validate();

        return $this->writes->run($year, function () use ($year, $matchId, $teamId, $memberId, $write, $publication) {
            abort_unless(config('tournament.enabled') === true, 404);
            $actor = User::whereKey(CompanyAuth::user()?->id)->sharedLock()->first();
            abort_unless($actor && $actor->is_scorer, 403);
            $reference = TournamentMatch::whereKey($matchId)->where('tournament_year', $year)->first();
            if (! $reference || ! in_array($reference->stage, ['G', 'QF', 'SF', 'F'], true)) {
                $this->reject('Select a match in this tournament year.');
            }
            $group = $reference->group_id === null ? null : TournamentGroup::whereKey($reference->group_id)->where('tournament_year', $year)->lockForUpdate()->first();
            $query = TournamentMatch::where('tournament_year', $year);
            if ($reference->stage === 'G') {
                $query->whereKey($matchId);
            }
            $matches = $query->orderBy('id')->lockForUpdate()->get();
            $match = $matches->firstWhere('id', $matchId);
            if (! $match || ($match->stage === 'G' && ! $group)
                || ! in_array($teamId, [$match->team1_id, $match->team2_id], true)) {
                $this->reject('Select a participating team from this match.');
            }
            if ($publication && ($match->status !== 'finished' || $match->result_published_at === null
                || ! in_array($match->result_type, ['win', 'tie'], true))) {
                $this->reject('Player publication requires an eligible published finished result.');
            }
            $ids = $match->stage === 'G' ? [$teamId] : [$match->team1_id, $match->team2_id];
            $teams = Team::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $query = GroupTeam::where('tournament_year', $year)->whereIn('team_id', $ids);
            if ($group) {
                $query->where('group_id', $group->id);
            }
            $assignments = $query->orderBy('id')->lockForUpdate()->get();
            if ($teams->count() !== count($ids) || $teams->contains(fn ($team) => ! $team->approved)
                || $assignments->count() !== count($ids)) {
                $this->reject('The participating teams must still be approved and assigned to the match edition.');
            }
            $this->transitions->assertParticipants($match, $teams, $assignments, $matches);

            return $this->rosters->withLockedMember($teamId, $memberId, function (TeamMember $member) use ($write, $match, $actor) {
                // Identity is resolved from the locked registration record, never
                // from submitted names/genders. Corrections retain stored snapshots.
                return $write($match, $member, $actor);
            });
        });
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['player' => $message]);
    }
}
