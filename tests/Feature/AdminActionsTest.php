<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AdminActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_a_team(): void
    {
        $team = Team::factory()->create(['approved' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::withCookie('company_token', (new JWTService)->generateToken($admin));

        Volt::test('admin.approve-team', ['team' => $team])->call('approve');

        $this->assertTrue($team->fresh()->approved);
    }

    public function test_a_non_admin_cannot_approve_a_team(): void
    {
        $team = Team::factory()->create(['approved' => false]);
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::withCookie('company_token', (new JWTService)->generateToken($user));

        Volt::test('admin.approve-team', ['team' => $team])->call('approve')->assertForbidden();

        $this->assertFalse($team->fresh()->approved);
    }

    public function test_approving_from_the_list_publishes_the_team_on_the_industry_page(): void
    {
        $team = Team::factory()->create(['team_name' => 'Hidden Hawks', 'approved' => false]);
        $this->get(route('teams.industry'))->assertDontSee('Hidden Hawks');

        Volt::test('admin.teams-list')->call('approveTeam', $team->id);

        $this->get(route('teams.industry'))->assertSee('Hidden Hawks');
    }

    public function test_teams_list_can_lock_and_unlock_a_team(): void
    {
        $team = Team::factory()->create(['locked' => false]);

        Volt::test('admin.teams-list')->call('toggleLock', $team->id);
        $this->assertTrue($team->fresh()->locked);

        Volt::test('admin.teams-list')->call('toggleLock', $team->id);
        $this->assertFalse($team->fresh()->locked);
    }

    public function test_teams_list_filters_pending_and_approved(): void
    {
        Team::factory()->create(['team_name' => 'Pending Pumas', 'approved' => false]);
        Team::factory()->create(['team_name' => 'Approved Aces', 'approved' => true]);

        Volt::test('admin.teams-list')->set('filter', 'pending')->assertSee('Pending Pumas')->assertDontSee('Approved Aces');
        Volt::test('admin.teams-list')->set('filter', 'approved')->assertSee('Approved Aces')->assertDontSee('Pending Pumas');
        Volt::test('admin.teams-list')->set('filter', 'all')->assertSee('Pending Pumas')->assertSee('Approved Aces');
    }

    public function test_admin_can_edit_a_team(): void
    {
        $team = Team::factory()->create();

        Volt::test('admin.edit-team', ['team' => $team])
            ->set('team_name', 'Renamed XI')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('Renamed XI', $team->fresh()->team_name);
    }

    public function test_editing_a_team_validates_the_team_name(): void
    {
        $team = Team::factory()->create();

        Volt::test('admin.edit-team', ['team' => $team])->set('team_name', '')->call('update')->assertHasErrors(['team_name']);
    }

    public function test_admin_can_delete_a_team_and_its_members(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(3)->create(['team_id' => $team->id]);

        Volt::test('admin.delete-team', ['team' => $team])->call('delete')->assertRedirect(route('admin.teams'));

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_admin_can_promote_a_user_without_a_company(): void
    {
        $user = User::factory()->create(['company_id' => null, 'is_admin' => false]);

        Volt::test('admin.users-list')->call('promoteToAdmin', $user->id);

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_a_user_with_a_company_cannot_be_promoted(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id, 'is_admin' => false]);

        Volt::test('admin.users-list')->call('promoteToAdmin', $user->id);

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_admin_demotion_is_not_available(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $component = Volt::test('admin.users-list')->assertDontSee('Demote');

        try {
            $component->call('demoteFromAdmin', $user->id);
            $this->fail('The removed demotion action should not be callable.');
        } catch (\Livewire\Exceptions\MethodNotFoundException $exception) {
            $this->assertTrue($user->fresh()->is_admin);
        }
    }

    public function test_users_list_can_show_only_admins(): void
    {
        User::factory()->create(['name' => 'Ada Admin', 'is_admin' => true]);
        User::factory()->create(['name' => 'Cora Company', 'is_admin' => false]);

        Volt::test('admin.users-list')->set('filter', 'admins')->assertSee('Ada Admin')->assertDontSee('Cora Company');
    }

    public function test_admin_can_change_a_companys_team_limit(): void
    {
        $company = Company::factory()->create(['max_team_count' => 2]);

        Volt::test('admin.companies-list')->set('maxTeamCount', 5)->call('updateMaxTeamCount', $company->id)->assertHasNoErrors();

        $this->assertSame(5, $company->fresh()->max_team_count);
    }

    public function test_team_limit_is_validated(): void
    {
        $company = Company::factory()->create(['max_team_count' => 2]);

        foreach ([-1, 101, 'many'] as $bad) {
            Volt::test('admin.companies-list')->set('maxTeamCount', $bad)->call('updateMaxTeamCount', $company->id)->assertHasErrors(['maxTeamCount']);
        }

        $this->assertSame(2, $company->fresh()->max_team_count);
    }

    public function test_admin_can_manage_team_members_and_the_captain_is_unique(): void
    {
        $team = Team::factory()->create();

        $component = Volt::test('admin.team-members-manager', ['team' => $team])
            ->set('member_name', 'First Captain')->set('gender', 'Male')->set('is_captain', true)->call('saveMember');
        $this->assertSame(1, $team->members()->count());

        $component->set('member_name', 'Second Captain')->set('gender', 'Female')->set('is_captain', true)->call('saveMember')
            ->assertHasErrors(['is_captain']);
        $this->assertSame(1, $team->members()->count());
    }
}
