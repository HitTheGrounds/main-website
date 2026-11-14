<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function enforceCsrf(): void
    {
        // Exercise the real web middleware, disabling only its test-suite bypass.
        // Keep APP_ENV=testing and all database/service safety guards intact.
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public static function accountTypes(): array
    {
        return [['representative'], ['admin'], ['scorer'], ['pending']];
    }

    #[DataProvider('accountTypes')]
    public function test_shared_logout_requires_csrf_for_every_jwt_account_type(string $role): void
    {
        $this->enforceCsrf();
        config(['tournament.enabled' => false]);
        $user = User::factory()->create([
            'is_admin' => $role === 'admin',
            'company_id' => $role === 'representative' ? Company::factory()->create()->id : null,
        ]);
        $user->is_scorer = $role === 'scorer';
        $user->save();
        $this->signedIn($user)->withSession(['_token' => 'test-csrf-token']);
        $before = $user->fresh()->jwt_token;
        $this->post(route('company.logout'))->assertStatus(419);
        $this->assertSame($before, $user->fresh()->jwt_token);
        $this->post(route('company.logout'), ['_token' => 'wrong-token'])->assertStatus(419);
        $this->assertSame($before, $user->fresh()->jwt_token);
        $this->post(route('company.logout'), ['_token' => 'test-csrf-token'])
            ->assertRedirect(route('home'))->assertCookieExpired('company_token');
        $this->assertNull($user->fresh()->jwt_token);
    }

    public function test_real_livewire_admin_mutation_requires_csrf(): void
    {
        $this->enforceCsrf();
        $team = Team::factory()->create(['approved' => false]);
        $this->signedIn(User::factory()->create(['is_admin' => true]))
            ->withSession(['_token' => 'test-csrf-token']);
        $html = $this->get('/admin/teams')->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $decoded = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (json_decode($decoded, true)['memo']['name'] === 'admin.teams-list') {
                $snapshot = $decoded;
                break;
            }
        }
        $this->assertNotNull($snapshot);
        $payload = ['components' => [[
            'snapshot' => $snapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => 'approveTeam', 'params' => [$team->id]]],
        ]]];
        $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertStatus(419);
        $this->assertFalse($team->fresh()->approved);
        $payload['_token'] = 'test-csrf-token';
        $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertOk();
        $this->assertTrue($team->fresh()->approved);
    }
}
