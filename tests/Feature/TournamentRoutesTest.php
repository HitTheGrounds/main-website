<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TournamentRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function registerTournamentRoutes(): void
    {
        config(['tournament.enabled' => true]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    private function scorer(): User
    {
        $scorer = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $scorer->is_scorer = true;
        $scorer->save();

        return $scorer;
    }

    public function test_disabled_feature_does_not_register_tournament_routes(): void
    {
        config(['tournament.enabled' => false]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        foreach (['/scorer/dashboard', '/scorer/groups', '/admin/tournament/scorers'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->assertFalse(Route::has('scorer.dashboard'));
        $this->assertFalse(Route::has('admin.tournament.scorers.index'));
    }

    public function test_registered_routes_remain_blocked_after_feature_is_disabled(): void
    {
        $this->registerTournamentRoutes();
        config(['tournament.enabled' => false]);
        $this->assertTrue(Route::has('scorer.dashboard'));
        foreach (['/scorer/dashboard', '/scorer/groups', '/admin/tournament/scorers'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_enabled_private_pages_send_guests_to_existing_login(): void
    {
        $this->registerTournamentRoutes();
        foreach (['/scorer/dashboard', '/scorer/groups', '/admin/tournament/scorers'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public static function rejectedTokens(): array
    {
        return [['invalid'], ['revoked'], ['expired']];
    }

    #[DataProvider('rejectedTokens')]
    public function test_tournament_pages_reject_invalid_revoked_and_expired_tokens(string $state): void
    {
        $this->registerTournamentRoutes();
        if ($state === 'expired') {
            config(['app.jwt_ttl' => -1]);
        }
        $user = $this->scorer();
        $service = new JWTService;
        $token = $service->generateToken($user);
        if ($state === 'revoked') {
            $service->revokeToken($user);
        } elseif ($state === 'invalid') {
            $token = 'not.a.valid.jwt';
        }
        foreach (['/scorer/dashboard', '/scorer/groups', '/admin/tournament/scorers'] as $path) {
            $this->withCookie('company_token', $token)->get($path)
                ->assertRedirect(route('login'))->assertCookieExpired('company_token');
        }
    }

    public function test_scorer_can_open_scorer_shell_but_not_admin_account_management(): void
    {
        $this->registerTournamentRoutes();
        $this->signedIn($this->scorer());
        $this->get('/scorer/dashboard')->assertOk()->assertSee('Scorer dashboard')->assertSee('Scorer navigation')
            ->assertSee('name="_token"', false)->assertSee(route('company.logout'))->assertSee('noindex, nofollow');
        $this->get('/scorer/groups')->assertOk()->assertSee('Group setup');
        $this->get('/admin/tournament/scorers')->assertForbidden();
    }

    public function test_admin_can_open_account_management_but_not_scorer_pages(): void
    {
        $this->registerTournamentRoutes();
        $this->signedIn(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/tournament/scorers')->assertOk()->assertSee('Scorer accounts');
        $this->get('/scorer/dashboard')->assertForbidden();
        $this->get('/scorer/groups')->assertForbidden();
    }

    public function test_representative_and_pending_accounts_cannot_open_tournament_tools(): void
    {
        $this->registerTournamentRoutes();
        foreach ([Company::factory()->create()->id, null] as $companyId) {
            $this->signedIn(User::factory()->create(['company_id' => $companyId, 'is_admin' => false]));
            foreach (['/scorer/dashboard', '/scorer/groups', '/admin/tournament/scorers'] as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }

    public function test_disabled_feature_keeps_existing_login_and_public_pages_available(): void
    {
        config(['tournament.enabled' => false]);
        $this->get(route('home'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('rules'))->assertOk();
    }

    public function test_scorer_can_log_out_using_the_existing_csrf_form_destination(): void
    {
        $this->registerTournamentRoutes();
        $this->signedIn($this->scorer());
        $this->post(route('company.logout'))->assertRedirect(route('home'));
        $this->get('/scorer/dashboard')->assertRedirect(route('login'));
    }

    public function test_scorer_workspace_skips_analytics_even_in_production(): void
    {
        $this->registerTournamentRoutes();
        $this->app['env'] = 'production';
        config(['services.google_analytics.id' => 'G-TESTONLY']);
        $this->signedIn($this->scorer());
        $this->get('/scorer/dashboard')->assertOk()->assertDontSee('googletagmanager.com', false);
        $this->get('/scorer/groups')->assertOk()->assertDontSee('googletagmanager.com', false);
    }
}
