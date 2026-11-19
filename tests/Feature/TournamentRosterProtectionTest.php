<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\JWTService;
use App\Services\Tournament\RosterProtectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\Support\TournamentRosterChecks;
use Tests\TestCase;

class TournamentRosterProtectionTest extends TestCase
{
    use RefreshDatabase, TournamentRosterChecks;

    private function loginAs(User $user): void
    {
        Livewire::withCookie('company_token', (new JWTService)->generateToken($user));
    }

    public function test_guest_and_dedicated_scorer_cannot_use_destructive_admin_actions(): void
    {
        $team = Team::factory()->create();
        $player = TeamMember::factory()->create(['team_id' => $team->id]);
        Volt::test('admin.delete-team', ['team' => $team])->call('delete')->assertForbidden();
        Volt::test('admin.team-members-manager', ['team' => $team])->call('deleteMember', $player->id)->assertForbidden();
        Volt::test('company.delete-team', ['team' => $team])->call('delete')->assertRedirect(route('company.teams'));
        Volt::test('company.team-members', ['team' => $team])->call('deleteMember', $player->id)->assertForbidden();
        $scorer = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $scorer->is_scorer = true;
        $scorer->save();
        $this->loginAs($scorer);
        Volt::test('admin.delete-team', ['team' => $team])->call('delete')->assertForbidden();
        Volt::test('admin.team-members-manager', ['team' => $team])->call('deleteMember', $player->id)->assertForbidden();
        Volt::test('company.team-members', ['team' => $team])->call('deleteMember', $player->id)->assertForbidden();
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('team_members', ['id' => $player->id]);
    }

    public function test_blocked_settings_account_deletion_keeps_the_user_signed_in(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $this->referenceTeam($team);
        $this->actingAs($owner);
        Volt::test('settings.delete-user-form')->set('password', 'password')->call('deleteUser')
            ->assertHasErrors(['password'])->assertSee('linked to tournament records');
        $this->assertAuthenticatedAs($owner);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_unrelated_settings_account_deletion_still_deletes_then_logs_out(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Volt::test('settings.delete-user-form')->set('password', 'password')->call('deleteUser')->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
    }

    private function referenceTeam(Team $team): void
    {
        $group = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'A']);
        DB::table('group_teams')->insert(['tournament_year' => 2026, 'group_id' => $group, 'team_id' => $team->id]);
    }

    private function referencePlayer(TeamMember $member): void
    {
        DB::table('player_tournament_stats')->insert([
            'tournament_year' => 2026, 'team_id' => $member->team_id, 'team_member_id' => $member->id,
            'player_name' => $member->name, 'player_gender' => $member->gender,
        ]);
    }

    public function test_owner_cannot_partially_delete_a_referenced_team_or_its_members(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('team-members/preserved.jpg', 'synthetic');
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $members = TeamMember::factory()->count(2)->create(['team_id' => $team->id, 'picture' => 'team-members/preserved.jpg']);
        $this->referenceTeam($team);
        $this->loginAs($owner);

        Volt::test('company.delete-team', ['team' => $team])->call('delete')->assertHasErrors(['team'])->assertSee('linked to tournament records');

        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertSame($members->modelKeys(), $team->members()->orderBy('id')->pluck('id')->all());
        Storage::disk('public')->assertExists('team-members/preserved.jpg');
    }

    public function test_admin_cannot_delete_a_referenced_team(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->create(['team_id' => $team->id]);
        $this->referenceTeam($team);
        $this->loginAs(User::factory()->create(['is_admin' => true]));

        Volt::test('admin.delete-team', ['team' => $team])->call('delete')->assertHasErrors(['team'])->assertSee('linked to tournament records');

        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertSame(1, $team->members()->count());
    }

    public function test_owner_and_admin_cannot_delete_a_referenced_player_or_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('team-members/player.jpg', 'synthetic');
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = TeamMember::factory()->create(['team_id' => $team->id, 'picture' => 'team-members/player.jpg']);
        $this->referencePlayer($member);
        foreach (['company.team-members' => $owner, 'admin.team-members-manager' => User::factory()->create(['is_admin' => true])] as $component => $actor) {
            $this->loginAs($actor);
            Volt::test($component, ['team' => $team])->call('deleteMember', $member->id)->assertHasErrors(['team']);
            $this->assertDatabaseHas('team_members', ['id' => $member->id]);
            Storage::disk('public')->assertExists('team-members/player.jpg');
        }
    }

    public function test_destructive_actions_check_the_existing_owner_or_admin_permission(): void
    {
        $team = Team::factory()->create();
        $member = TeamMember::factory()->create(['team_id' => $team->id]);
        $this->loginAs(User::factory()->create(['is_admin' => false]));

        Volt::test('admin.delete-team', ['team' => $team])->call('delete')->assertForbidden();
        Volt::test('admin.team-members-manager', ['team' => $team])->call('deleteMember', $member->id)->assertForbidden();
        Volt::test('company.team-members', ['team' => $team])->call('deleteMember', $member->id)->assertForbidden();

        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
    }

    public function test_rolling_back_member_deletion_preserves_the_member_and_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('team-members/rollback.jpg', 'synthetic');
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = TeamMember::factory()->create(['team_id' => $team->id, 'picture' => 'team-members/rollback.jpg']);
        DB::beginTransaction();
        try {
            app(RosterProtectionService::class)->deleteMember($team->id, $member->id, $owner);
            $this->assertDatabaseMissing('team_members', ['id' => $member->id]);
            Storage::disk('public')->assertExists('team-members/rollback.jpg');
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
        Storage::disk('public')->assertExists('team-members/rollback.jpg');
    }

    public function test_referenced_players_cannot_move_but_unrelated_moves_and_normal_edits_work(): void
    {
        $member = TeamMember::factory()->create();
        $destination = Team::factory()->create();
        $this->referencePlayer($member);
        $originalTeam = $member->team_id;
        try {
            $member->update(['team_id' => $destination->id]);
            $this->fail('Referenced player was moved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team', $exception->errors());
        }
        $this->assertSame($originalTeam, $member->fresh()->team_id);
        $member->refresh()->update(['name' => 'Corrected name']);
        $this->assertSame('Corrected name', $member->fresh()->name);
        $unrelated = TeamMember::factory()->create();
        $unrelated->update(['team_id' => $destination->id]);
        $this->assertSame($destination->id, $unrelated->fresh()->team_id);
    }

    public function test_parent_account_deletion_cannot_cascade_into_tournament_teams(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $member = TeamMember::factory()->create(['team_id' => $team->id]);
        $this->referenceTeam($team);
        try {
            app(RosterProtectionService::class)->deleteAccount($owner);
            $this->fail('Referenced owner account was deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('team', $exception->errors());
        }
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('team_members', ['id' => $member->id]);
    }
}
