<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Tournament\RosterProtectionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TournamentRosterCommitTest extends TestCase
{
    // Real outer commits on the guarded memory database; RefreshDatabase would
    // intentionally keep afterCommit callbacks pending until its test rollback.
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        // These tests replace/rollback whole memory schemas. Do not let adjacent
        // RefreshDatabase tests restore a cached PDO whose tables were dropped.
        RefreshDatabaseState::$inMemoryConnections = [];
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$inMemoryConnections = [];
        });
    }

    public function test_photo_is_removed_only_after_the_outermost_successful_commit(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('team-members/commit.jpg', 'synthetic');
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $player = TeamMember::factory()->create(['team_id' => $team->id, 'picture' => 'team-members/commit.jpg']);
        DB::beginTransaction();
        try {
            app(RosterProtectionService::class)->deleteMember($team->id, $player->id, $owner);
            Storage::disk('public')->assertExists('team-members/commit.jpg');
            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        Storage::disk('public')->assertMissing('team-members/commit.jpg');
        $this->assertDatabaseMissing('team_members', ['id' => $player->id]);
    }

    public function test_rollback_discards_photo_cleanup_even_after_a_later_commit(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('team-members/rollback-later.jpg', 'synthetic');
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $player = TeamMember::factory()->create(['team_id' => $team->id, 'picture' => 'team-members/rollback-later.jpg']);
        DB::beginTransaction();
        try {
            app(RosterProtectionService::class)->deleteMember($team->id, $player->id, $owner);
        } finally {
            DB::rollBack();
        }
        DB::transaction(fn () => $team->update(['team_name' => 'Unrelated edit']));
        Storage::disk('public')->assertExists('team-members/rollback-later.jpg');
        $this->assertDatabaseHas('team_members', ['id' => $player->id]);
    }

    public function test_management_works_before_feature_migrations_are_applied(): void
    {
        $paths = array_merge(glob(database_path('migrations/2026_11_01_*.php')), glob(database_path('migrations/2026_11_14_*.php')));
        rsort($paths);
        foreach ($paths as $path) {
            (require $path)->down();
            DB::table('migrations')->where('migration', basename($path, '.php'))->delete();
        }
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $destination = Team::factory()->create();
        $player = TeamMember::factory()->create(['team_id' => $team->id]);
        $player->update(['team_id' => $destination->id]);
        $this->assertSame($destination->id, $player->fresh()->team_id);
        $service = app(RosterProtectionService::class);
        $this->assertTrue($service->deleteMember($destination->id, $player->id, $destination->user));
        TeamMember::factory()->create(['team_id' => $team->id]);
        $service->deleteTeam($team->id, $owner);
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_late_reference_failure_rolls_back_prior_member_deletions(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $members = TeamMember::factory()->count(2)->create(['team_id' => $team->id]);
        $injected = false;
        DB::connection()->beforeExecuting(function ($sql) use (&$injected, $team) {
            if (! $injected && str_starts_with($sql, 'delete from "teams"')) {
                $injected = true;
                $group = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'Late reference']);
                DB::table('group_teams')->insert(['tournament_year' => 2026, 'group_id' => $group, 'team_id' => $team->id]);
            }
        });
        try {
            app(RosterProtectionService::class)->deleteTeam($team->id, $owner);
            $this->fail('Late reference was ignored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team', $exception->errors());
        }
        $this->assertTrue($injected);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertSame($members->modelKeys(), $team->members()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('group_teams', 0);
    }
}
