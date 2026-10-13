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

    public function test_company_cannot_create_a_team_with_a_duplicate_name(): void
    {
        $user = $this->companyUser(maxTeams: 5);
        Team::factory()->create(['company_id' => $user->company_id, 'team_name' => 'Code Crushers']);
        $this->as($user);

        foreach (['Code Crushers', ' code crushers '] as $name) {
            Volt::test('company.team-form')
                ->set('team_name', $name)
                ->set('captain_email', 'cap@acme.test')
                ->set('captain_phone', '0771234567')
                ->call('save')
                ->assertHasErrors(['team_name']);
        }

        $this->assertDatabaseCount('teams', 1);
    }

    public function test_company_cannot_rename_a_team_to_another_team_name(): void
    {
        $user = $this->companyUser();
        Team::factory()->create(['company_id' => $user->company_id, 'team_name' => 'Code Crushers']);
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id, 'team_name' => 'Original Name']);
        $this->as($user);

        foreach (['Code Crushers', ' code crushers '] as $name) {
            Volt::test('company.edit-team', ['team' => $team])
                ->set('team_name', $name)
                ->call('update')
                ->assertHasErrors(['team_name']);
        }

        $this->assertSame('Original Name', $team->fresh()->team_name);
    }

    public function test_company_can_keep_its_team_name_when_editing(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id, 'team_name' => 'Code Crushers']);
        $this->as($user);

        Volt::test('company.edit-team', ['team' => $team])
            ->set('captain_phone', '0770000000')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('0770000000', $team->fresh()->captain_phone);
    }

    public function test_different_companies_can_use_the_same_team_name_when_creating_and_editing(): void
    {
        Team::factory()->create(['team_name' => 'Shared Name']);
        $user = $this->companyUser();
        $this->as($user);

        Volt::test('company.team-form')
            ->set('team_name', ' Shared Name ')
            ->set('captain_email', 'cap@acme.test')
            ->set('captain_phone', '0771234567')
            ->call('save')
            ->assertHasNoErrors();

        $team = Team::where('company_id', $user->company_id)->firstOrFail();
        $this->assertSame('Shared Name', $team->team_name);
        Team::factory()->create(['team_name' => 'Other Shared Name']);

        Volt::test('company.edit-team', ['team' => $team])
            ->set('team_name', ' Other Shared Name ')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertSame('Other Shared Name', $team->fresh()->team_name);
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

    public function test_player_names_must_be_unique_within_the_team_on_add_and_edit(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $first = TeamMember::factory()->create(['team_id' => $team->id, 'name' => 'Alex Silva']);
        $second = TeamMember::factory()->create(['team_id' => $team->id, 'name' => 'Sam Silva']);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->call('openAddModal')->set('member_name', ' alex silva ')->set('gender', 'Male')
            ->call('saveMember')->assertHasErrors(['member_name']);

        Volt::test('company.team-members', ['team' => $team])
            ->call('openEditModal', $second->id)->set('member_name', 'ALEX SILVA')
            ->call('saveMember')->assertHasErrors(['member_name']);

        $this->assertSame('Sam Silva', $second->fresh()->name);
        $this->assertSame(2, $team->members()->count());

        Volt::test('company.team-members', ['team' => $team])
            ->call('openEditModal', $first->id)->call('saveMember')->assertHasNoErrors();
    }

    public function test_players_with_the_same_name_can_belong_to_different_teams(): void
    {
        TeamMember::factory()->create(['name' => 'Alex Silva']);
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->set('member_name', ' Alex Silva ')->set('gender', 'Male')
            ->call('saveMember')->assertHasNoErrors();

        $this->assertSame('Alex Silva', $team->members()->firstOrFail()->name);
    }

    public function test_gender_limits_are_enforced_when_adding_and_changing_players(): void
    {
        $user = $this->companyUser();
        $this->as($user);

        foreach (['Male' => 9, 'Female' => 3] as $gender => $limit) {
            $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
            TeamMember::factory()->count($limit - 1)->create(['team_id' => $team->id, 'gender' => $gender]);

            Volt::test('company.team-members', ['team' => $team])
                ->set('member_name', 'Last Slot')->set('gender', $gender)
                ->call('saveMember')->assertHasNoErrors();

            Volt::test('company.team-members', ['team' => $team->fresh()])
                ->set('member_name', 'Extra Player')->set('gender', $gender)
                ->call('saveMember')->assertHasErrors(['gender']);

            $opposite = $gender === 'Male' ? 'Female' : 'Male';
            $member = TeamMember::factory()->create(['team_id' => $team->id, 'gender' => $opposite]);
            Volt::test('company.team-members', ['team' => $team->fresh()])
                ->call('openEditModal', $member->id)->set('gender', $gender)
                ->call('saveMember')->assertHasErrors(['gender']);
            $this->assertSame($opposite, $member->fresh()->gender);

            $existing = $team->members()->where('gender', $gender)->firstOrFail();
            Volt::test('company.team-members', ['team' => $team->fresh()])
                ->call('openEditModal', $existing->id)->set('member_name', 'Updated Player')
                ->call('saveMember')->assertHasNoErrors();
            $this->assertSame($limit, $team->members()->where('gender', $gender)->count());
        }
    }

    public function test_full_team_explains_why_members_cannot_be_added(): void
    {
        $user = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $this->fullSquad($team);
        $this->as($user);

        Volt::test('company.team-members', ['team' => $team])
            ->assertSee('Add Member')
            ->assertSee('All 12 player slots are filled.');
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
