<?php

namespace Tests\Feature;

use App\Helpers\CompanyAuth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DatabaseSeederRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_one_account_per_role(): void
    {
        config(['tournament.enabled' => true]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();

        $this->seed();

        foreach ([
            'admin@example.com' => 'admin.dashboard',
            'scorer@example.com' => 'scorer.dashboard',
            'devil@hell.com' => 'company.dashboard',
        ] as $email => $route) {
            $this->assertSame($route, CompanyAuth::dashboardRoute(User::where('email', $email)->firstOrFail()), $email);
        }
    }
}
