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

class CompanyTeamsTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(int $maxTeams = 2): User
    {
        $company = Company::factory()->create(['max_team_count' => $maxTeams]);

        return User::factory()->create(['company_id' => $company->id]);
    }

    private function as(User $user): void
    {
        Livewire::withCookie('company_token', (new JWTService)->generateToken($user));
    }

    private function fullSquad(Team $team): void
    {
        TeamMember::factory()->count(9)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female']);
    }

    public function test_company_can_register_a_team(): void
    {
        $user = $this->companyUser();
        $this->as($user);

        Volt::test('company.team-form')
            ->set('team_name', 'Code Crushers')
            ->set('captain_email', 'cap@acme.test')
            ->set('captain_phone', '0771234567')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('company.teams'));

        $team = Team::firstOrFail();
        $this->assertSame($user->id, $team->user_id);
        $this->assertSame($user->company_id, $team->company_id);
        $this->assertFalse($team->approved);
        $this->assertFalse($team->locked);
    }

    public function test_team_form_validates_its_fields(): void
    {
        $this->as($this->companyUser());

        Volt::test('company.team-form')
            ->set('team_name', '')->set('captain_email', 'bad')->set('captain_phone', str_repeat('7', 30))
            ->call('save')
            ->assertHasErrors(['team_name', 'captain_email', 'captain_phone']);

        $this->assertDatabaseCount('teams', 0);
    }

    public function test_company_cannot_exceed_its_team_limit(): void
    {
        $user = $this->companyUser(maxTeams: 1);
        Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $this->as($user);

        Volt::test('company.team-form')
            ->set('team_name', 'One Too Many')->set('captain_email', 'cap@acme.test')->set('captain_phone', '0771234567')
            ->call('save')
            ->assertHasErrors(['team_name']);

        $this->assertDatabaseCount('teams', 1);
    }

    public function test_team_registration_can_be_closed(): void
    {
        config(['registration.team_enabled' => false]);
        $this->as($this->companyUser());

        Volt::test('company.team-form')
            ->set('team_name', 'Late Team')->set('captain_email', 'cap@acme.test')->set('captain_phone', '0771234567')
            ->call('save')
            ->assertHasErrors(['team_name']);

        $this->assertDatabaseCount('teams', 0);
    }

    public function test_owner_can_add_members_but_a_squad_is_capped_at_twelve(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $this->fullSquad($team);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', 'Thirteenth')->set('gender', 'Male')
            ->call('saveMember')
            ->assertHasErrors(['member_name']);

        $this->assertSame(12, $team->members()->count());
    }

    public function test_member_gender_must_be_valid(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', 'Someone')->set('gender', 'Robot')
            ->call('saveMember')
            ->assertHasErrors(['gender']);
    }

    public function test_a_locked_team_cannot_be_changed(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id, 'locked' => true]);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', 'Late Joiner')->set('gender', 'Male')
            ->call('saveMember');

        $this->assertSame(0, $team->members()->count());
    }

    public function test_team_locks_only_with_a_full_squad(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        TeamMember::factory()->count(5)->create(['team_id' => $team->id, 'gender' => 'Male']);
        $this->as($user);

        Volt::test('company.lock-team', ['team' => $team])->call('lock');
        $this->assertFalse($team->fresh()->locked);

        TeamMember::factory()->count(4)->create(['team_id' => $team->id, 'gender' => 'Male']);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id, 'gender' => 'Female']);

        Volt::test('company.lock-team', ['team' => $team->fresh()])->call('lock');
        $this->assertTrue($team->fresh()->locked);
    }

    public function test_only_the_owner_can_lock_or_delete_a_team(): void
    {
        $owner = $this->companyUser();
        $intruder = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $owner->id, 'company_id' => $owner->company_id]);
        $this->fullSquad($team);

        $this->as($intruder);
        Volt::test('company.lock-team', ['team' => $team])->call('lock');
        Volt::test('company.delete-team', ['team' => $team])->call('delete');

        $this->assertFalse($team->fresh()->locked);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_owner_can_delete_a_team_with_its_members(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        TeamMember::factory()->count(3)->create(['team_id' => $team->id]);
        $this->as($user);

        Volt::test('company.delete-team', ['team' => $team])->call('delete')->assertRedirect(route('company.teams'));

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_company_profile_description_is_validated(): void
    {
        $user = $this->companyUser();
        $this->as($user);

        Volt::test('company.edit-company-profile', ['company' => $user->company])
            ->set('description', str_repeat('a', 5001))
            ->call('updateDescription')
            ->assertHasErrors(['description']);

        Volt::test('company.edit-company-profile', ['company' => $user->company])
            ->set('description', 'We write software.')
            ->call('updateDescription')
            ->assertHasNoErrors();

        $this->assertSame('We write software.', $user->company->fresh()->description);
    }
}
