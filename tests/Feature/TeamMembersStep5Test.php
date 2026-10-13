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

class TeamMembersStep5Test extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::factory()->create(['max_team_count' => 5]);
        return User::factory()->create(['company_id' => $company->id]);
    }

    private function as(User $user): void
    {
        Livewire::withCookie('company_token', (new JWTService)->generateToken($user));
    }

    public function test_cannot_add_duplicate_member_name_case_insensitive(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        TeamMember::factory()->create(['team_id' => $team->id, 'name' => 'Alice Smith', 'gender' => 'Female']);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', 'alice smith')
            ->set('gender', 'Female')
            ->call('saveMember')
            ->assertHasErrors(['member_name' => 'A member with this name already exists in this team.']);

        $this->assertCount(1, $team->members);
    }

    public function test_can_edit_member_retaining_same_name(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $member = TeamMember::factory()->create(['team_id' => $team->id, 'name' => 'Alice Smith', 'gender' => 'Female']);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->call('openEditModal', $member->id)
            ->set('member_name', 'Alice Smith')
            ->call('saveMember')
            ->assertHasNoErrors();

        $this->assertSame('Alice Smith', $member->fresh()->name);
    }

    public function test_cannot_edit_member_to_another_existing_name(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $member1 = TeamMember::factory()->create(['team_id' => $team->id, 'name' => 'Alice Smith', 'gender' => 'Female']);
        $member2 = TeamMember::factory()->create(['team_id' => $team->id, 'name' => 'Bob Jones', 'gender' => 'Male']);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->call('openEditModal', $member2->id)
            ->set('member_name', 'ALICE SMITH')
            ->call('saveMember')
            ->assertHasErrors(['member_name' => 'A member with this name already exists in this team.']);

        $this->assertSame('Bob Jones', $member2->fresh()->name);
    }

    public function test_cannot_exceed_male_player_slots_on_add(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        TeamMember::factory()->count(9)->create(['team_id' => $team->id, 'gender' => 'Male']);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', 'Tenth Male')
            ->set('gender', 'Male')
            ->call('saveMember')
            ->assertHasErrors(['gender' => 'Male player slots are full (9/9).']);

        $this->assertSame(9, $team->getMaleCount());
    }

    public function test_cannot_exceed_female_player_slots_on_add(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female']);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', 'Fourth Female')
            ->set('gender', 'Female')
            ->call('saveMember')
            ->assertHasErrors(['gender' => 'Female player slots are full (3/3).']);

        $this->assertSame(3, $team->getFemaleCount());
    }

    public function test_cannot_change_gender_on_edit_if_target_slots_are_full(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female']);
        $male = TeamMember::factory()->create(['team_id' => $team->id, 'gender' => 'Male', 'name' => 'Charlie']);
        $this->as($user);

        // Female slots are full (3/3), changing Charlie from Male -> Female should fail
        Volt::test('company.team-members', ['team' => $team])
            ->call('openEditModal', $male->id)
            ->set('gender', 'Female')
            ->call('saveMember')
            ->assertHasErrors(['gender' => 'Female player slots are full (3/3). Cannot change gender.']);

        $this->assertSame('Male', $male->fresh()->gender);
    }

    public function test_can_edit_existing_member_when_slots_are_full_if_gender_unchanged(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $male = TeamMember::factory()->create(['team_id' => $team->id, 'gender' => 'Male', 'name' => 'Charlie']);
        TeamMember::factory()->count(8)->create(['team_id' => $team->id, 'gender' => 'Male']); // 9 males total
        $this->as($user);

        $this->assertSame(9, $team->getMaleCount());

        // Editing Charlie's name while 9 males exist should succeed
        Volt::test('company.team-members', ['team' => $team])
            ->call('openEditModal', $male->id)
            ->set('member_name', 'Charles')
            ->call('saveMember')
            ->assertHasNoErrors();

        $this->assertSame('Charles', $male->fresh()->name);
    }

    public function test_captain_requires_email_and_phone(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', 'Captain Jack')
            ->set('gender', 'Male')
            ->set('is_captain', true)
            ->set('captain_email', '')
            ->set('captain_phone', '')
            ->call('saveMember')
            ->assertHasErrors(['captain_email', 'captain_phone']);

        $this->assertCount(0, $team->members);
    }

    public function test_saving_captain_syncs_to_teams_table(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', 'Captain Jack')
            ->set('gender', 'Male')
            ->set('is_captain', true)
            ->set('captain_email', 'jack@example.com')
            ->set('captain_phone', '0771234567')
            ->call('saveMember')
            ->assertHasNoErrors();

        $member = $team->members()->first();
        $this->assertTrue($member->is_captain);
        $this->assertSame('jack@example.com', $member->email);
        $this->assertSame('0771234567', $member->phone);

        // Check teams table sync
        $freshTeam = $team->fresh();
        $this->assertSame('jack@example.com', $freshTeam->captain_email);
        $this->assertSame('0771234567', $freshTeam->captain_phone);
    }

    public function test_cannot_have_more_than_one_captain(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'name' => 'Existing Captain',
            'is_captain' => true,
            'email' => 'cap@example.com',
            'phone' => '0771111111',
        ]);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', 'New Guy')
            ->set('gender', 'Male')
            ->set('is_captain', true)
            ->set('captain_email', 'new@example.com')
            ->set('captain_phone', '0772222222')
            ->call('saveMember')
            ->assertHasErrors(['is_captain' => 'Team can only have one captain.']);
    }

    public function test_unmarking_captain_clears_team_captain_contact(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'captain_email' => 'jack@example.com',
            'captain_phone' => '0771234567',
        ]);
        $captain = TeamMember::factory()->create([
            'team_id' => $team->id,
            'name' => 'Captain Jack',
            'gender' => 'Male',
            'is_captain' => true,
            'email' => 'jack@example.com',
            'phone' => '0771234567',
        ]);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->call('openEditModal', $captain->id)
            ->set('is_captain', false)
            ->call('saveMember')
            ->assertHasNoErrors();

        $freshMember = $captain->fresh();
        $this->assertFalse($freshMember->is_captain);
        $this->assertNull($freshMember->email);
        $this->assertNull($freshMember->phone);

        $freshTeam = $team->fresh();
        $this->assertNull($freshTeam->captain_email);
        $this->assertNull($freshTeam->captain_phone);
    }

    public function test_deleting_captain_clears_team_captain_contact(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'captain_email' => 'jack@example.com',
            'captain_phone' => '0771234567',
        ]);
        $captain = TeamMember::factory()->create([
            'team_id' => $team->id,
            'name' => 'Captain Jack',
            'gender' => 'Male',
            'is_captain' => true,
            'email' => 'jack@example.com',
            'phone' => '0771234567',
        ]);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->call('deleteMember', $captain->id);

        $freshTeam = $team->fresh();
        $this->assertNull($freshTeam->captain_email);
        $this->assertNull($freshTeam->captain_phone);
    }

    public function test_cannot_lock_team_without_captain(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        TeamMember::factory()->count(9)->create(['team_id' => $team->id, 'gender' => 'Male', 'is_captain' => false]);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female', 'is_captain' => false]);
        $this->as($user);

        Volt::test('company.lock-team', ['team' => $team])
            ->call('lock')
            ->assertDispatched('error', function ($event, $params) {
                return ($params['message'] ?? '') === 'Team must have 12 players (9M, 3F) and an assigned captain before locking.';
            });

        $this->assertFalse($team->fresh()->locked);
    }

    public function test_can_lock_team_with_full_squad_and_captain(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        TeamMember::factory()->count(8)->create(['team_id' => $team->id, 'gender' => 'Male', 'is_captain' => false]);
        TeamMember::factory()->create(['team_id' => $team->id, 'gender' => 'Male', 'is_captain' => true, 'email' => 'c@a.test', 'phone' => '0771234567']);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female', 'is_captain' => false]);
        $this->as($user);

        Volt::test('company.lock-team', ['team' => $team])
            ->call('lock')
            ->assertDispatched('team-locked');

        $this->assertTrue($team->fresh()->locked);
    }
}

