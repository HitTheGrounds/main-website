<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TournamentGroup;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class TournamentGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    private function getAdmin()
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_create_group(): void
    {
        $admin = $this->getAdmin();

        $this->actingAs($admin);
        Volt::test('admin.group-manager')
            ->set('newGroupName', 'Group A')
            ->call('createGroup')
            ->assertHasNoErrors()
            ->assertDispatched('group-created');

        $this->assertDatabaseHas('tournament_groups', [
            'name' => 'Group A'
        ]);
    }

    public function test_group_name_must_be_unique(): void
    {
        $admin = $this->getAdmin();
        TournamentGroup::create(['name' => 'Group A']);

        $this->actingAs($admin);
        Volt::test('admin.group-manager')
            ->set('newGroupName', 'Group A')
            ->call('createGroup')
            ->assertHasErrors(['newGroupName' => 'unique']);
    }

    public function test_admin_can_assign_approved_team(): void
    {
        $admin = $this->getAdmin();
        $group = TournamentGroup::create(['name' => 'Group A']);
        
        $company = Company::factory()->create();
        $team = Team::factory()->create([
            'company_id' => $company->id,
            'approved' => true
        ]);

        $this->actingAs($admin);
        Volt::test('admin.group-manager')
            ->set("teamAssignments.{$group->id}", $team->id)
            ->call('assignTeam', $group->id)
            ->assertHasNoErrors()
            ->assertDispatched('team-assigned');

        $this->assertDatabaseHas('group_teams', [
            'group_id' => $group->id,
            'team_id' => $team->id
        ]);
    }

    public function test_cannot_assign_team_already_in_a_group(): void
    {
        $admin = $this->getAdmin();
        $group1 = TournamentGroup::create(['name' => 'Group A']);
        $group2 = TournamentGroup::create(['name' => 'Group B']);
        
        $company = Company::factory()->create();
        $team = Team::factory()->create([
            'company_id' => $company->id,
            'approved' => true
        ]);

        // Assign to Group A
        \App\Models\GroupTeam::create([
            'group_id' => $group1->id,
            'team_id' => $team->id
        ]);

        // Attempt to assign to Group B
        $this->actingAs($admin);
        Volt::test('admin.group-manager')
            ->set("teamAssignments.{$group2->id}", $team->id)
            ->call('assignTeam', $group2->id)
            ->assertHasErrors(["assign.{$group2->id}"]);
            
        $this->assertDatabaseMissing('group_teams', [
            'group_id' => $group2->id,
            'team_id' => $team->id
        ]);
    }

    public function test_only_approved_teams_are_shown_as_unassigned(): void
    {
        $admin = $this->getAdmin();
        $company = Company::factory()->create();
        $approvedTeam = Team::factory()->create(['company_id' => $company->id, 'approved' => true]);
        $unapprovedTeam = Team::factory()->create(['company_id' => $company->id, 'approved' => false]);
        
        TournamentGroup::create(['name' => 'Group A']);

        $this->actingAs($admin);
        $component = Volt::test('admin.group-manager');
        
        $component->assertSee($approvedTeam->team_name);
        $component->assertDontSee($unapprovedTeam->team_name);
    }
}
