<?php

namespace Tests\Support;

use App\Models\Company;
use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\Tournament\TournamentGroupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait TournamentGroupChecks
{
    private function scorer(): User
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $user = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $user->is_scorer = true;
        $user->save();
        request()->attributes->set('user', $user);

        return $user;
    }

    private function groupService(): TournamentGroupService
    {
        return app(TournamentGroupService::class);
    }

    private function rejected(string $field, callable $write): void
    {
        try {
            $write();
            $this->fail('Expected protected group setup rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public static function groupSizes(): array
    {
        return [
            'current seven groups' => [[4, 4, 4, 4, 4, 4, 4], 42],
            'previous four groups' => [[5, 5, 5, 5], 40],
            'varying sizes including empty' => [[0, 1, 2, 6], 16],
        ];
    }

    #[DataProvider('groupSizes')]
    public function test_dynamic_setup_reuses_teams_and_derives_expected_pair_coverage(array $sizes, int $expected): void
    {
        $this->scorer();
        $teams = Team::factory()->count(array_sum($sizes))->create(['approved' => true]);
        $before = DB::table('teams')->orderBy('id')->get()->toJson();
        $next = 0;
        foreach ($sizes as $index => $size) {
            $group = $this->groupService()->create(2026, 'Group '.($index + 1));
            for ($i = 0; $i < $size; $i++) {
                $this->groupService()->assign(2026, $group->id, $group->fresh()->version, $teams[$next++]->id);
            }
        }
        $setup = $this->groupService()->setup(2026);
        $this->assertCount(count($sizes), $setup['groups']);
        $this->assertSame($sizes, $setup['groups']->pluck('team_count')->all());
        $this->assertSame($expected, $setup['expected_matches']);
        $this->assertCount(0, $setup['unassigned_teams']);
        $this->assertSame($before, DB::table('teams')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('group_teams', array_sum($sizes));
    }

    public function test_group_names_are_trimmed_unique_by_year_and_version_checked(): void
    {
        $this->scorer();
        $group = $this->groupService()->create(2026, ' A ');
        $this->assertSame('A', $group->name);
        $this->rejected('name', fn () => $this->groupService()->create(2026, 'a'));
        $this->rejected('name', fn () => $this->groupService()->create(2026, '  '));
        $this->rejected('name', fn () => $this->groupService()->create(2026, str_repeat('x', 101)));
        $this->groupService()->create(2027, 'A');
        $renamed = $this->groupService()->rename(2026, $group->id, 1, ' B ');
        $this->assertSame('B', $renamed->name);
        $this->assertSame(2, $renamed->version);
        $this->rejected('tournament', fn () => $this->groupService()->rename(2026, $group->id, 1, 'Old form'));
        $this->rejected('group', fn () => $this->groupService()->rename(2027, $group->id, 2, 'Wrong year'));
        $this->assertSame('B', $group->fresh()->name);
    }

    public function test_only_unassigned_approved_teams_can_be_added_and_stale_forms_fail(): void
    {
        $this->scorer();
        $group = $this->groupService()->create(2026, 'A');
        $other = $this->groupService()->create(2026, 'B');
        $team = Team::factory()->create(['approved' => true]);
        $pending = Team::factory()->create(['approved' => false]);
        $this->rejected('team', fn () => $this->groupService()->assign(2026, $group->id, 1, $pending->id));
        $this->rejected('team', fn () => $this->groupService()->assign(2026, $group->id, 1, 999999));
        $this->rejected('group', fn () => $this->groupService()->assign(2027, $group->id, 1, $team->id));
        $this->groupService()->assign(2026, $group->id, 1, $team->id);
        $this->rejected('tournament', fn () => $this->groupService()->remove(2026, $group->id, 1, $team->id));
        $this->rejected('team', fn () => $this->groupService()->assign(2026, $other->id, 1, $team->id));
        $this->rejected('team', fn () => $this->groupService()->assign(2026, $group->id, 2, $team->id));
        $this->assertCount(0, $this->groupService()->setup(2026)['unassigned_teams']);
        $this->assertDatabaseCount('group_teams', 1);
        $this->assertSame(2, $group->fresh()->version);
        $this->assertSame(1, $other->fresh()->version);
    }

    public function test_unused_membership_can_be_removed_then_assigned_elsewhere_without_deleting_team(): void
    {
        $this->scorer();
        $group = $this->groupService()->create(2026, 'A');
        $other = $this->groupService()->create(2026, 'B');
        $team = Team::factory()->create(['approved' => true]);
        $this->groupService()->assign(2026, $group->id, 1, $team->id);
        $this->rejected('group', fn () => $this->groupService()->delete(2026, $group->id, 2));
        $this->groupService()->remove(2026, $group->id, 2, $team->id);
        $this->assertSame([$team->id], $this->groupService()->setup(2026)['unassigned_teams']->modelKeys());
        $this->groupService()->assign(2026, $other->id, 1, $team->id);
        $this->groupService()->delete(2026, $group->id, 3);
        $this->assertDatabaseMissing('tournament_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('group_teams', ['team_id' => $team->id, 'group_id' => $other->id]);
    }

    private function fixture(int $year, int $groupId, int $team1, int $team2, string $stage = 'G'): void
    {
        DB::table('matches')->insert([
            'tournament_year' => $year, 'group_id' => $stage === 'G' ? $groupId : null,
            'stage' => $stage, 'bracket_position' => $stage === 'G' ? null : 1,
            'team1_id' => $team1, 'team2_id' => $team2, 'balls_per_over' => 4,
        ]);
    }

    public function test_fixtures_freeze_membership_but_allow_safe_label_correction(): void
    {
        $this->scorer();
        $group = $this->groupService()->create(2026, 'A');
        $teams = Team::factory()->count(3)->create(['approved' => true]);
        $this->groupService()->assign(2026, $group->id, 1, $teams[0]->id);
        $this->groupService()->assign(2026, $group->id, 2, $teams[1]->id);
        $this->fixture(2026, $group->id, $teams[0]->id, $teams[1]->id);
        $this->rejected('group', fn () => $this->groupService()->assign(2026, $group->id, 3, $teams[2]->id));
        $this->rejected('group', fn () => $this->groupService()->remove(2026, $group->id, 3, $teams[0]->id));
        $this->rejected('group', fn () => $this->groupService()->delete(2026, $group->id, 3));
        $this->groupService()->rename(2026, $group->id, 3, 'Corrected label');
        $this->assertDatabaseCount('group_teams', 2);
        $this->assertDatabaseCount('matches', 1);
    }

    public static function dependentStates(): array
    {
        return [['qualification'], ['knockout'], ['standings'], ['adjustment'], ['player totals']];
    }

    #[DataProvider('dependentStates')]
    public function test_saved_dependencies_block_membership_changes(string $state): void
    {
        $this->scorer();
        $group = $this->groupService()->create(2026, 'A');
        $team = Team::factory()->create(['approved' => true]);
        $assignment = $this->groupService()->assign(2026, $group->id, 1, $team->id);
        if ($state === 'qualification') {
            DB::table('group_teams')->where('id', $assignment->id)->update(['qualified' => true]);
        } elseif ($state === 'knockout') {
            $this->fixture(2026, $group->id, $team->id, Team::factory()->create()->id, 'QF');
        } elseif ($state === 'standings') {
            DB::table('group_teams')->where('id', $assignment->id)->update(['played' => 1]);
        } elseif ($state === 'adjustment') {
            DB::table('group_teams')->where('id', $assignment->id)->update(['point_adjustments' => json_encode([['points' => -1, 'reason' => 'Test penalty']])]);
        } else {
            $member = \App\Models\TeamMember::factory()->create(['team_id' => $team->id]);
            DB::table('player_tournament_stats')->insert([
                'tournament_year' => 2026, 'team_id' => $team->id, 'team_member_id' => $member->id,
                'player_name' => $member->name, 'player_gender' => $member->gender,
            ]);
        }
        $this->rejected($state === 'player totals' ? 'team' : 'group', fn () => $this->groupService()->remove(2026, $group->id, 2, $team->id));
        $this->assertDatabaseHas('group_teams', ['id' => $assignment->id]);
        $this->assertSame(2, $group->fresh()->version);
    }

    public function test_qualification_locks_edition_setup_and_old_years_do_not_block_new_year(): void
    {
        $this->scorer();
        $old = $this->groupService()->create(2025, 'Old');
        $empty = $this->groupService()->create(2025, 'Empty');
        $team = Team::factory()->create(['approved' => true]);
        $assignment = $this->groupService()->assign(2025, $old->id, 1, $team->id);
        DB::table('group_teams')->where('id', $assignment->id)->update(['qualified' => true]);
        $this->rejected('group', fn () => $this->groupService()->create(2025, 'Too late'));
        $this->rejected('group', fn () => $this->groupService()->delete(2025, $empty->id, 1));
        $new = $this->groupService()->create(2026, 'New');
        $this->groupService()->assign(2026, $new->id, 1, $team->id);
        $this->assertDatabaseCount('group_teams', 2);
        $this->assertTrue(GroupTeam::findOrFail($assignment->id)->qualified);
    }

    public static function rejectedGroupActors(): array
    {
        return [['guest'], ['representative'], ['pending'], ['admin'], ['revoked scorer'], ['disabled']];
    }

    #[DataProvider('rejectedGroupActors')]
    public function test_all_service_entry_points_enforce_feature_and_fresh_scorer_permission(string $role): void
    {
        $actor = $this->scorer();
        $group = $this->groupService()->create(2026, 'A');
        $team = Team::factory()->create(['approved' => true]);
        if ($role === 'guest') {
            request()->attributes->remove('user');
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
        } elseif ($role === 'revoked scorer') {
            DB::table('users')->where('id', $actor->id)->update(['is_scorer' => false]);
        } else {
            $actor = User::factory()->create([
                'is_admin' => $role === 'admin',
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null,
            ]);
            request()->attributes->set('user', $actor);
        }
        foreach ([
            fn () => $this->groupService()->setup(2026),
            fn () => $this->groupService()->create(2026, 'Injected'),
            fn () => $this->groupService()->rename(2026, $group->id, 1, 'Injected'),
            fn () => $this->groupService()->delete(2026, $group->id, 1),
            fn () => $this->groupService()->assign(2026, $group->id, 1, $team->id),
            fn () => $this->groupService()->remove(2026, $group->id, 1, $team->id),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected scorer-only service rejection.');
            } catch (HttpException $exception) {
                $this->assertSame($role === 'disabled' ? 404 : 403, $exception->getStatusCode());
            }
        }
        $this->assertSame('A', $group->fresh()->name);
        $this->assertDatabaseCount('tournament_groups', 1);
        $this->assertDatabaseCount('group_teams', 0);
    }
}
