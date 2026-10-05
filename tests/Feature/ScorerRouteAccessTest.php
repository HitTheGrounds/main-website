<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScorerRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    private JWTService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = new JWTService();
    }

    private function actingAsUser(User $user)
    {
        $token = $this->jwtService->generateToken($user);
        return $this->withCookie('company_token', $token);
    }

    public function test_scorer_can_access_scorer_dashboard(): void
    {
        $scorer = User::factory()->create(['role' => 'scorer']);

        $response = $this->actingAsUser($scorer)->get('/scorer/dashboard');
        
        $response->assertStatus(200);
        $response->assertSee('Scorer Dashboard');
    }

    public function test_scorer_cannot_access_admin_dashboard(): void
    {
        $scorer = User::factory()->create(['role' => 'scorer']);

        $response = $this->actingAsUser($scorer)->get('/admin/dashboard');
        
        $response->assertForbidden();
    }

    public function test_scorer_cannot_access_company_dashboard(): void
    {
        $scorer = User::factory()->create(['role' => 'scorer']);

        $response = $this->actingAsUser($scorer)->get('/company/dashboard');
        
        $response->assertForbidden();
    }

    public function test_company_user_cannot_access_scorer_dashboard(): void
    {
        $companyUser = User::factory()->create(['role' => 'company']);

        $response = $this->actingAsUser($companyUser)->get('/scorer/dashboard');
        
        $response->assertForbidden();
    }

    public function test_admin_user_cannot_access_scorer_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAsUser($admin)->get('/scorer/dashboard');
        
        $response->assertForbidden();
    }
}
