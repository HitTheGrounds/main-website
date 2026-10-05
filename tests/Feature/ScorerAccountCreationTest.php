<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ScorerAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_scorer_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);

        Volt::test('admin.create-scorer-modal')
            ->set('name', 'Test Scorer')
            ->set('email', 'scorer@test.com')
            ->call('createScorer')
            ->assertHasNoErrors()
            ->assertDispatched('scorer-created');

        $this->assertDatabaseHas('users', [
            'email' => 'scorer@test.com',
            'role' => 'scorer',
            'company_id' => null,
        ]);
    }

    public function test_non_admin_cannot_create_scorer_account(): void
    {
        $companyUser = User::factory()->create(['role' => 'company']);

        $this->actingAs($companyUser);

        Volt::test('admin.create-scorer-modal')
            ->set('name', 'Test Scorer')
            ->set('email', 'scorer2@test.com')
            ->call('createScorer')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'scorer2@test.com',
        ]);
    }

    public function test_unauthenticated_user_cannot_create_scorer_account(): void
    {
        request()->attributes->remove('user');

        Volt::test('admin.create-scorer-modal')
            ->set('name', 'Test Scorer')
            ->set('email', 'scorer3@test.com')
            ->call('createScorer')
            ->assertForbidden();
            
        $this->assertDatabaseMissing('users', [
            'email' => 'scorer3@test.com',
        ]);
    }
}
