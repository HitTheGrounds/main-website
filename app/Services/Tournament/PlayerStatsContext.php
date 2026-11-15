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
    public function __construct(private TournamentWriteService $writes, private RosterProtectionService $rosters) {}

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
            if (! $reference || $reference->stage !== 'G') {
                $this->reject('Select a group match in this tournament year. Knockout scoring is not available yet.');
            }
            $group = TournamentGroup::whereKey($reference->group_id)->where('tournament_year', $year)->lockForUpdate()->first();
            $match = TournamentMatch::whereKey($matchId)->where('tournament_year', $year)->lockForUpdate()->first();
            if (! $group || ! $match || $match->stage !== 'G' || $match->group_id !== $group->id
                || ! in_array($teamId, [$match->team1_id, $match->team2_id], true)) {
                $this->reject('Select a participating team from this match and group.');
            }
            if ($publication && ($match->status !== 'finished' || $match->result_published_at === null
                || ! in_array($match->result_type, ['win', 'tie'], true))) {
                $this->reject('Player publication requires an eligible published finished result.');
            }
            $team = Team::whereKey($teamId)->lockForUpdate()->first();
            $assignment = GroupTeam::where('group_id', $group->id)->where('tournament_year', $year)
                ->where('team_id', $teamId)->lockForUpdate()->first();
            if (! $team || ! $team->approved || ! $assignment) {
                $this->reject('This team must still be approved and assigned to the match group.');
            }

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
