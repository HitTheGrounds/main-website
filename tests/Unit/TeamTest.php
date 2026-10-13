<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $team->user->id);
    }

    public function test_team_belongs_to_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $team = Team::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $this->assertEquals($company->id, $team->company->id);
    }

    public function test_team_has_members_relationship(): void
    {
        $team = Team::factory()->create();
        $member = TeamMember::factory()->create(['team_id' => $team->id]);

        $this->assertTrue($team->members->contains($member));
    }

    public function test_team_can_identify_captain(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->create(['team_id' => $team->id, 'is_captain' => false]);
        $captain = TeamMember::factory()->create(['team_id' => $team->id, 'is_captain' => true]);

        $this->assertEquals($captain->id, $team->captain()->id);
    }

    public function test_team_counts_male_members_correctly(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(6)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(2)->create(['team_id' => $team->id, 'gender' => 'Female']);

        $this->assertEquals(6, $team->getMaleCount());
    }

    public function test_team_counts_female_members_correctly(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(6)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(2)->create(['team_id' => $team->id, 'gender' => 'Female']);

        $this->assertEquals(2, $team->getFemaleCount());
    }

    public function test_team_meets_the_required_squad(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(9)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female']);

        $this->assertTrue($team->meetsMinimumRequirements());
    }

    public function test_team_does_not_meet_minimum_requirements_with_insufficient_males(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(5)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(2)->create(['team_id' => $team->id, 'gender' => 'Female']);

        $this->assertFalse($team->meetsMinimumRequirements());
    }

    public function test_team_does_not_meet_minimum_requirements_with_insufficient_females(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(6)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(1)->create(['team_id' => $team->id, 'gender' => 'Female']);

        $this->assertFalse($team->meetsMinimumRequirements());
    }

    public function test_team_does_not_meet_minimum_requirements_with_insufficient_total(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(5)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(1)->create(['team_id' => $team->id, 'gender' => 'Female']);

        $this->assertFalse($team->meetsMinimumRequirements());
    }

    public function test_team_is_valid_with_a_full_squad(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(8)->create(['team_id' => $team->id, 'gender' => 'Male', 'is_captain' => false]);
        TeamMember::factory()->create(['team_id' => $team->id, 'gender' => 'Male', 'is_captain' => true]);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female', 'is_captain' => false]);

        $this->assertTrue($team->isValidConfiguration());
    }

    public function test_team_is_valid_with_maximum_configuration(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(8)->create(['team_id' => $team->id, 'gender' => 'Male', 'is_captain' => false]);
        TeamMember::factory()->create(['team_id' => $team->id, 'gender' => 'Male', 'is_captain' => true]);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female', 'is_captain' => false]);

        $this->assertTrue($team->isValidConfiguration());
    }

    public function test_team_is_invalid_without_a_captain(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(9)->create(['team_id' => $team->id, 'gender' => 'Male', 'is_captain' => false]);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female', 'is_captain' => false]);

        $this->assertFalse($team->isValidConfiguration());
    }

    public function test_team_is_invalid_with_too_many_males(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(10)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(2)->create(['team_id' => $team->id, 'gender' => 'Female']);

        $this->assertFalse($team->isValidConfiguration());
    }

    public function test_team_is_invalid_with_too_many_females(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(6)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(4)->create(['team_id' => $team->id, 'gender' => 'Female']);

        $this->assertFalse($team->isValidConfiguration());
    }

    public function test_team_is_invalid_with_too_many_total_members(): void
    {
        $team = Team::factory()->create();
        TeamMember::factory()->count(10)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female']);

        $this->assertFalse($team->isValidConfiguration());
    }

    public function test_team_locked_status_is_boolean(): void
    {
        $team = Team::factory()->create(['locked' => true]);

        $this->assertTrue($team->locked);
        $this->assertIsBool($team->locked);
    }

    public function test_team_approved_status_is_boolean(): void
    {
        $team = Team::factory()->create(['approved' => true]);

        $this->assertTrue($team->approved);
        $this->assertIsBool($team->approved);
    }
}
