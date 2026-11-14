<?php

namespace App\Services\Tournament;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RosterProtectionService
{
    /**
     * Scoring services must use this inside their authorized write transaction.
     * Recheck membership after locking, not from a previously loaded player list.
     */
    public function withLockedMember(int $teamId, int $memberId, callable $write): mixed
    {
        return DB::transaction(function () use ($teamId, $memberId, $write) {
            Team::whereKey($teamId)->lockForUpdate()->firstOrFail();
            $member = TeamMember::where('team_id', $teamId)->whereKey($memberId)->lockForUpdate()->first();
            if (! $member) {
                throw ValidationException::withMessages([
                    'team' => 'This player no longer belongs to this team. Refresh the player list and try again.',
                ]);
            }

            return $write($member);
        });
    }

    public function deleteTeam(int $teamId, User $actor, bool $asAdmin = false): void
    {
        $this->protect(function () use ($teamId, $actor, $asAdmin) {
            $team = Team::whereKey($teamId)->lockForUpdate()->firstOrFail();
            $this->authorize($team, $actor, $asAdmin);
            $members = $team->members()->orderBy('id')->lockForUpdate()->get();
            $this->assertTeamUnreferenced($team, $members->modelKeys());

            // Keep both operations inside the transaction. A late FK failure must
            // restore every member, including members without recorded figures.
            $team->members()->delete();
            $team->delete();
        });
    }

    public function deleteMember(int $teamId, int $memberId, User $actor, bool $asAdmin = false): bool
    {
        return $this->protect(function () use ($teamId, $memberId, $actor, $asAdmin) {
            $team = Team::whereKey($teamId)->lockForUpdate()->firstOrFail();
            $this->authorize($team, $actor, $asAdmin);
            if (! $asAdmin && $team->locked) {
                throw ValidationException::withMessages(['team' => 'This team is locked and cannot be modified.']);
            }
            $member = $team->members()->whereKey($memberId)->lockForUpdate()->first();
            if (! $member) {
                return false;
            }
            $this->assertMemberUnreferenced($member);
            $picture = $member->picture;
            $member->delete();

            // Database rollback cannot restore a file. Delete it only after the
            // outermost transaction commits, including when called inside another.
            if ($picture) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($picture));
            }

            return true;
        });
    }

    public function deleteAccount(User $user): void
    {
        $this->protect(function () use ($user) {
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            foreach ($account->teams()->orderBy('id')->lockForUpdate()->get() as $team) {
                $members = $team->members()->orderBy('id')->lockForUpdate()->get();
                $this->assertTeamUnreferenced($team, $members->modelKeys());
            }
            $account->delete();
        });
    }

    public function assertMemberUnreferenced(TeamMember $member): void
    {
        foreach (['player_match_stats', 'player_tournament_stats'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('team_member_id', $member->id)->exists()) {
                $this->blocked();
            }
        }
    }

    private function assertTeamUnreferenced(Team $team, array $memberIds): void
    {
        // Check table existence, not the feature flag: history remains protected
        // when public scoring is disabled, while pre-migration management still works.
        if (Schema::hasTable('group_teams') && DB::table('group_teams')->where('team_id', $team->id)->exists()) {
            $this->blocked();
        }
        if (Schema::hasTable('matches') && DB::table('matches')->where(function ($query) use ($team) {
            $query->where('team1_id', $team->id)->orWhere('team2_id', $team->id)
                ->orWhere('batting_first_id', $team->id)->orWhere('winner_id', $team->id);
        })->exists()) {
            $this->blocked();
        }
        foreach (['player_match_stats', 'player_tournament_stats'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where(function ($query) use ($team, $memberIds) {
                $query->where('team_id', $team->id)->orWhereIn('team_member_id', $memberIds);
            })->exists()) {
                $this->blocked();
            }
        }
    }

    private function authorize(Team $team, User $actor, bool $asAdmin): void
    {
        abort_unless($asAdmin ? $actor->is_admin : (! $actor->is_scorer && $team->user_id === $actor->id), 403);
    }

    private function protect(callable $operation): mixed
    {
        try {
            return DB::transaction($operation);
        } catch (QueryException $exception) {
            // Restrictive FKs remain the final guard if a reference races the check.
            if (str_starts_with((string) $exception->getCode(), '23')) {
                $this->blocked();
            }
            throw $exception;
        }
    }

    private function blocked(): never
    {
        throw ValidationException::withMessages([
            'team' => 'This record is linked to tournament records and cannot be removed or moved to another team.',
        ]);
    }
}
