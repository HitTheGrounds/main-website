<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function companyUser(): User
    {
        return User::factory()->create(['company_id' => Company::factory()->create()->id]);
    }

    public function test_guest_is_sent_to_login_from_company_pages(): void
    {
        foreach (['company.dashboard', 'company.profile', 'company.teams', 'company.teams.create'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_signed_in_company_user_can_open_company_pages(): void
    {
        $user = $this->companyUser();

        foreach (['company.dashboard', 'company.profile', 'company.teams', 'company.teams.create'] as $route) {
            $this->signedIn($user)->get(route($route))->assertOk();
        }
    }

    public function test_garbage_token_is_rejected_and_the_cookie_is_cleared(): void
    {
        $this->withCookie('company_token', 'not.a.jwt')
            ->get(route('company.dashboard'))
            ->assertRedirect(route('login'))
            ->assertCookieExpired('company_token');
    }

    public function test_token_for_a_deleted_user_is_rejected(): void
    {
        $user = $this->companyUser();
        $token = (new JWTService)->generateToken($user);
        $user->delete();

        $this->withCookie('company_token', $token)->get(route('company.dashboard'))->assertRedirect(route('login'));
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->companyUser();
        $token = (new JWTService)->generateToken($user);

        $this->withCookie('company_token', $token)->post(route('company.logout'))->assertRedirect(route('home'));

        $this->assertNull($user->fresh()->jwt_token);
        $this->withCookie('company_token', $token)->get(route('company.dashboard'))->assertRedirect(route('login'));
    }

    public function test_signed_in_user_is_sent_away_from_login_and_register(): void
    {
        $user = $this->companyUser();

        $this->signedIn($user)->get(route('login'))->assertRedirect(route('company.dashboard'));
        $this->signedIn($user)->get(route('register'))->assertRedirect(route('company.dashboard'));
    }

    public function test_company_user_cannot_open_any_admin_page(): void
    {
        $team = Team::factory()->create();
        $user = $this->companyUser();

        foreach ([
            route('admin.dashboard'), route('admin.companies'), route('admin.teams'), route('admin.users'),
            route('admin.teams.show', $team), route('admin.teams.export'), route('teams.university'),
        ] as $url) {
            $this->signedIn($user)->get($url)->assertForbidden();
        }
    }

    public function test_admin_can_open_every_admin_page(): void
    {
        $team = Team::factory()->create();
        $admin = $this->admin();

        foreach ([
            route('admin.dashboard'), route('admin.companies'), route('admin.teams'), route('admin.users'),
            route('admin.teams.show', $team), route('admin.teams.export'), route('teams.university'),
        ] as $url) {
            $this->signedIn($admin)->get($url)->assertOk();
        }
    }

    public function test_company_user_cannot_open_another_users_team(): void
    {
        $owner = $this->companyUser();
        $other = $this->companyUser();
        $team = Team::factory()->create(['user_id' => $owner->id, 'company_id' => $owner->company_id]);

        $this->signedIn($other)->get(route('company.teams.show', $team))->assertForbidden();
        $this->signedIn($owner)->get(route('company.teams.show', $team))->assertOk();
    }

    public function test_team_export_can_be_filtered_to_locked_teams(): void
    {
        $locked = Team::factory()->create(['team_name' => 'Locked Lions', 'locked' => true]);
        $open = Team::factory()->create(['team_name' => 'Open Owls', 'locked' => false]);
        $admin = $this->admin();

        $this->signedIn($admin)->get(route('admin.teams.export'))
            ->assertOk()->assertSee('Locked Lions')->assertSee('Open Owls');

        $this->signedIn($admin)->get(route('admin.teams.export', ['type' => 'locked']))
            ->assertOk()->assertSee('Locked Lions')->assertDontSee('Open Owls');
    }
}
