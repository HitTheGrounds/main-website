<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unapproved_user_sees_approval_instructions_without_dashboard_access(): void
    {
        $user = User::factory()->create(['is_admin' => false, 'company_id' => null]);

        $this->signedIn($user)->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertViewIs('admin.awaiting-approval')
            ->assertSee('Web Development Team')
            ->assertSee('Organizing Committee of Hit The Ground')
            ->assertSee($user->email)
            ->assertSee('errors/403_Unauthorized.jpg');

        $this->signedIn($user)->get(route('admin.users'))->assertForbidden();

        $user->update(['is_admin' => true]);

        $this->signedIn($user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.dashboard');
    }

    public function test_company_user_cannot_see_admin_approval_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'company_id' => Company::factory()->create()->id,
        ]);

        $this->signedIn($user)->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertDontSee('Your admin access is awaiting approval')
            ->assertDontSee('Web Development Team')
            ->assertDontSee($user->email);
    }

    public function test_admin_register_page_can_be_rendered(): void
    {
        $response = $this->get(route('admin.register'));

        $response->assertStatus(200);
    }

    public function test_admin_dashboard_requires_authentication(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_pages_require_authentication(): void
    {
        $response = $this->get(route('admin.companies'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.users'));
        $response->assertRedirect(route('login'));

        $response = $this->get(route('admin.teams'));
        $response->assertRedirect(route('login'));
    }
}
