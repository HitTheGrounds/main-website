<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyAreaAccessTest extends TestCase
{
    use RefreshDatabase;

    private function owner(bool $scorer = false, bool $admin = false): User
    {
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id, 'is_admin' => $admin]);
        $user->is_scorer = $scorer;
        $user->save();

        return $user;
    }

    private function paths(Team $team): array
    {
        return ['/company/dashboard', '/company/profile', '/company/teams', '/company/teams/create', route('company.teams.show', $team)];
    }

    public function test_scorer_cannot_open_company_tools_even_with_stale_ownership(): void
    {
        config(['tournament.enabled' => false]);
        $user = $this->owner(scorer: true);
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
        $this->signedIn($user);
        foreach ($this->paths($team) as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_existing_representatives_and_company_associated_admins_keep_access(): void
    {
        foreach ([false, true] as $admin) {
            $user = $this->owner(admin: $admin);
            $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id]);
            $this->signedIn($user);
            foreach ($this->paths($team) as $path) {
                $this->get($path)->assertOk();
            }
        }
    }

    public static function mutations(): array
    {
        return [
            ['team-form', 'save'], ['edit-team', 'update'], ['lock-team', 'lock'],
            ['delete-team', 'delete'], ['team-members', 'saveMember'],
            ['team-members', 'deleteMember'], ['edit-company-profile', 'updateCompany'],
            ['edit-company-profile', 'updateDescription'],
        ];
    }

    #[DataProvider('mutations')]
    public function test_scorer_is_rejected_by_company_actions_directly(string $component, string $method): void
    {
        $user = $this->owner(scorer: true);
        $team = Team::factory()->create(['user_id' => $user->id, 'company_id' => $user->company_id, 'locked' => false]);
        $member = TeamMember::factory()->create(['team_id' => $team->id]);
        $before = [$team->fresh()->getAttributes(), $member->fresh()->getAttributes(), $user->company->fresh()->getAttributes()];
        Livewire::withCookie('company_token', (new JWTService)->generateToken($user));
        Volt::test('company.'.$component, ['team' => $team, 'company' => $user->company])
            ->call($method, ...($method === 'deleteMember' ? [$member->id] : []))
            ->assertForbidden();
        $this->assertSame($before, [$team->fresh()->getAttributes(), $member->fresh()->getAttributes(), $user->company->fresh()->getAttributes()]);
        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('team_members', 1);
    }

    public function test_all_jwt_account_types_can_logout_with_feature_disabled(): void
    {
        config(['tournament.enabled' => false]);
        $users = [$this->owner(), $this->owner(admin: true), User::factory()->create(['company_id' => null])];
        $scorer = User::factory()->create(['company_id' => null]);
        $scorer->is_scorer = true;
        $scorer->save();
        $users[] = $scorer;
        foreach ($users as $user) {
            $this->signedIn($user)->post(route('company.logout'))
                ->assertRedirect(route('home'))->assertCookieExpired('company_token');
            $this->assertNull($user->fresh()->jwt_token);
        }
        $middleware = Route::getRoutes()->getByName('company.logout')->gatherMiddleware();
        $this->assertContains('web', $middleware);
        $this->assertContains('auth.company', $middleware);
        $this->assertNotContains('company.area', $middleware);
        $this->get('/company/logout')->assertStatus(405);
    }

    public function test_guest_company_requests_still_redirect_to_login(): void
    {
        $this->get('/company/dashboard')->assertRedirect(route('login'));
    }
}
