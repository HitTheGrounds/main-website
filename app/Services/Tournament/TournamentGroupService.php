<?php

namespace App\Services\Tournament;

use App\Helpers\CompanyAuth;
use App\Models\GroupTeam;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Support\ScorerWriteThrottle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TournamentGroupService
{
    public function __construct(private TournamentWriteService $writes) {}

    public function setup(int $year): array
    {
        $this->authorize();
        $this->validateYear($year);
        $groups = TournamentGroup::where('tournament_year', $year)
            ->with(['assignments' => fn ($query) => $query->orderBy('team_id'), 'assignments.team:id,team_name'])
            ->orderBy('name')->orderBy('id')->get()->map(fn ($group) => [
                'id' => $group->id, 'name' => $group->name, 'version' => $group->version,
                'teams' => $group->assignments->map(fn ($assignment) => [
                    'id' => $assignment->team_id, 'name' => $assignment->team->team_name,
                ]),
                'team_count' => $group->assignments->count(),
                'expected_matches' => intdiv($group->assignments->count() * ($group->assignments->count() - 1), 2),
            ]);

        return [
            'groups' => $groups,
            'unassigned_teams' => Team::where('approved', true)
                ->whereDoesntHave('groupAssignments', fn ($query) => $query->where('tournament_year', $year))
                ->orderBy('team_name')->orderBy('id')->get(['id', 'team_name']),
            'expected_matches' => $groups->sum('expected_matches'),
        ];
    }

    public function create(int $year, string $name): TournamentGroup
    {
        return $this->mutate($year, function () use ($year, $name) {
            $this->assertEditionOpen($year);
            $group = new TournamentGroup;
            $group->tournament_year = $year;
            $group->name = $this->validatedName($year, $name);
            $group->save();

            return $group->refresh();
        });
    }

    public function rename(int $year, int $groupId, int $version, string $name): TournamentGroup
    {
        return $this->mutate($year, function () use ($year, $groupId, $version, $name) {
            $group = $this->group($year, $groupId, $version);
            // Renaming a display label does not change participants or coverage.
            $group->name = $this->validatedName($year, $name, $group->id);
            $group->version++;
            $group->save();

            return $group;
        });
    }

    public function delete(int $year, int $groupId, int $version): void
    {
        $this->mutate($year, function () use ($year, $groupId, $version) {
            $group = $this->group($year, $groupId, $version);
            $this->assertMembershipOpen($group);
            if ($group->assignments()->exists()) {
                $this->reject('group', 'Remove the unused team assignments before deleting this group.');
            }
            $group->delete();
        });
    }

    public function assign(int $year, int $groupId, int $version, int $teamId): GroupTeam
    {
        return $this->mutate($year, function () use ($year, $groupId, $version, $teamId) {
            $group = $this->group($year, $groupId, $version);
            $this->assertMembershipOpen($group);
            $team = Team::whereKey($teamId)->lockForUpdate()->first();
            if (! $team || ! $team->approved) {
                $this->reject('team', 'Select an existing approved team.');
            }
            if (GroupTeam::where('tournament_year', $year)->where('team_id', $teamId)->exists()) {
                $this->reject('team', 'This team already belongs to a group for this tournament year. Reload the team list.');
            }
            $this->assertTeamUnused($year, $teamId);
            $assignment = new GroupTeam;
            $assignment->tournament_year = $year;
            $assignment->group_id = $group->id;
            $assignment->team_id = $team->id;
            $assignment->save();
            $group->version++;
            $group->save();

            return $assignment->refresh();
        });
    }

    public function remove(int $year, int $groupId, int $version, int $teamId): void
    {
        $this->mutate($year, function () use ($year, $groupId, $version, $teamId) {
            $group = $this->group($year, $groupId, $version);
            $this->assertMembershipOpen($group);
            Team::whereKey($teamId)->lockForUpdate()->firstOrFail();
            $assignment = $group->assignments()->where('team_id', $teamId)->lockForUpdate()->first();
            if (! $assignment) {
                $this->reject('team', 'This team is no longer assigned to this group. Reload the group.');
            }
            $this->assertTeamUnused($year, $teamId);
            $assignment->delete();
            $group->version++;
            $group->save();
        });
    }

    private function mutate(int $year, callable $write): mixed
    {
        ScorerWriteThrottle::hit();
        $this->validateYear($year);

        return $this->writes->run($year, function () use ($write) {
            $this->authorize(); // Fresh permission check after waiting for the year lock.

            return $write();
        });
    }

    private function authorize(): void
    {
        abort_unless(config('tournament.enabled') === true, 404);
        $id = CompanyAuth::user()?->id;
        abort_unless($id && User::whereKey($id)->where('is_scorer', true)->exists(), 403);
    }

    private function validateYear(int $year): void
    {
        Validator::make(['year' => $year], ['year' => ['required', 'integer', 'between:1,9999']])->validate();
    }

    private function group(int $year, int $id, int $version): TournamentGroup
    {
        $group = TournamentGroup::where('tournament_year', $year)->whereKey($id)->lockForUpdate()->first();
        if (! $group) {
            $this->reject('group', 'Select an existing group in this tournament year.');
        }
        if ($group->version !== $version) {
            $this->reject('tournament', 'This group changed while you were editing. Reload it before saving.');
        }

        return $group;
    }

    private function validatedName(int $year, string $name, ?int $except = null): string
    {
        $name = trim($name);
        Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:100']])->validate();
        $query = TournamentGroup::where('tournament_year', $year)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
        if ($except !== null) {
            $query->where('id', '!=', $except);
        }
        if ($query->exists()) {
            $this->reject('name', 'A group with this name already exists in this tournament year.');
        }

        return $name;
    }

    private function assertEditionOpen(int $year): void
    {
        if (GroupTeam::where('tournament_year', $year)->where('qualified', true)->exists()
            || TournamentMatch::where('tournament_year', $year)->whereIn('stage', ['PO', 'QF', 'SF', 'F'])->exists()) {
            $this->reject('group', 'Group setup is locked because qualification or knockout matches depend on it.');
        }
    }

    private function assertMembershipOpen(TournamentGroup $group): void
    {
        $this->assertEditionOpen($group->tournament_year);
        if ($group->matches()->exists()) {
            $this->reject('group', 'This group already has fixtures. Its team assignments cannot change.');
        }
        foreach ($group->assignments()->get() as $assignment) {
            if ($assignment->played || $assignment->wins || $assignment->losses || $assignment->ties
                || $assignment->no_results || $assignment->base_points || $assignment->points
                || $assignment->runs_for || $assignment->runs_against || $assignment->point_adjustments
                || (float) $assignment->nrr_overs_faced || (float) $assignment->nrr_overs_bowled
                || $assignment->nrr !== null) {
                $this->reject('group', 'Saved standings or point adjustments depend on this group. Its assignments cannot change.');
            }
        }
    }

    private function assertTeamUnused(int $year, int $teamId): void
    {
        if (TournamentMatch::where('tournament_year', $year)
            ->where(fn ($query) => $query->where('team1_id', $teamId)->orWhere('team2_id', $teamId))->exists()
            || PlayerTournamentStat::where('tournament_year', $year)->where('team_id', $teamId)->exists()
            || Team::findOrFail($teamId)->playerMatchStats()
                ->whereHas('tournamentMatch', fn ($query) => $query->where('tournament_year', $year))->exists()) {
            $this->reject('team', 'Saved matches or player figures depend on this team assignment.');
        }
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
