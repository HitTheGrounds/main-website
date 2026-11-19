<?php

namespace Tests\Feature;

use App\Helpers\CompanyAuth;
use App\Models\Company;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardDestinationTest extends TestCase
{
    use RefreshDatabase;

    public static function accountTypes(): array
    {
        return [
            'representative' => [false, false, true, false, 'company.dashboard'],
            'admin' => [true, false, false, false, 'admin.dashboard'],
            'admin with company' => [true, false, true, true, 'admin.dashboard'],
            'awaiting approval' => [false, false, false, true, 'admin.dashboard'],
            'scorer enabled' => [false, true, false, true, 'scorer.dashboard'],
            'scorer disabled' => [false, true, false, false, 'home'],
        ];
    }

    #[DataProvider('accountTypes')]
    public function test_login_guest_redirects_and_public_dashboard_links_agree(
        bool $admin, bool $scorer, bool $company, bool $enabled, string $destination
    ): void {
        config(['tournament.enabled' => $enabled]);
        if ($enabled) {
            Route::middleware('web')->group(base_path('routes/tournament.php'));
            Route::getRoutes()->refreshNameLookups();
        }
        $user = User::factory()->create([
            'is_admin' => $admin,
            'company_id' => $company ? Company::factory()->create()->id : null,
        ]);
        $user->is_scorer = $scorer;
        $user->save();
        $this->assertSame($destination, CompanyAuth::dashboardRoute($user));

        RateLimiter::clear('verify-code:'.$user->id);
        VerificationCode::create(['user_id' => $user->id, 'code' => '123456', 'expires_at' => now()->addMinutes(15)]);
        Volt::test('company-login-form')
            ->set('userId', $user->id)->set('verification_code', '123456')
            ->call('verify')->assertHasNoErrors()
            ->assertRedirect(route($destination, absolute: false));
        $this->assertNotNull($user->fresh()->jwt_token);
        $this->assertTrue(VerificationCode::first()->is_used);

        $this->signedIn($user);
        foreach (['login', 'register'] as $guestRoute) {
            $this->get(route($guestRoute))->assertRedirect(route($destination));
        }
        foreach (['/', '/registrations'] as $page) {
            $html = $this->get($page)->assertOk()->getContent();
            preg_match_all('/<a\b[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/s', $html, $links, PREG_SET_ORDER);
            $dashboardLinks = array_filter($links, fn ($link) => str_contains(strip_tags($link[2]), 'Dashboard'));
            $this->assertNotEmpty($dashboardLinks, "The navbar must offer a dashboard link on {$page}.");
            foreach ($dashboardLinks as $link) {
                $this->assertSame(route($destination), html_entity_decode($link[1]));
            }
        }

        if (! $admin && ! $scorer && ! $company) {
            $this->get(route($destination))->assertForbidden()->assertViewIs('admin.awaiting-approval');
            $this->get('/admin/users')->assertForbidden();
        } else {
            $this->get(route($destination))->assertOk();
        }
        $this->assertSame($admin, $user->fresh()->is_admin);
        $this->assertSame($scorer, $user->fresh()->is_scorer);
    }

    public function test_enabled_scorer_falls_back_safely_when_routes_were_not_registered(): void
    {
        config(['tournament.enabled' => true]);
        $this->assertFalse(Route::has('scorer.dashboard'));
        $user = User::factory()->create(['company_id' => null]);
        $user->is_scorer = true;
        $user->save();
        $this->assertSame('home', CompanyAuth::dashboardRoute($user));
        $this->signedIn($user)->get('/login')->assertRedirect(route('home'));
    }

    public function test_disabled_flag_overrides_previously_registered_scorer_routes(): void
    {
        config(['tournament.enabled' => true]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        config(['tournament.enabled' => false]);
        $user = User::factory()->create(['company_id' => null]);
        $user->is_scorer = true;
        $user->save();
        $this->assertSame('home', CompanyAuth::dashboardRoute($user));
        $this->signedIn($user)->get('/login')->assertRedirect(route('home'));
        $this->get('/scorer/dashboard')->assertNotFound();
    }
}
