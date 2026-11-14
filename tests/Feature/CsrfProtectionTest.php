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

    public function test_real_group_creation_requires_csrf(): void
    {
        $this->enforceCsrf();
        config(['tournament.enabled' => true]);
        \Illuminate\Support\Facades\Route::middleware('web')->group(base_path('routes/tournament.php'));
        \Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();
        $scorer = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $scorer->is_scorer = true;
        $scorer->save();
        $this->signedIn($scorer)->withSession(['_token' => 'test-csrf-token']);
        $html = $this->get('/scorer/groups')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $payload = ['components' => [[
            'snapshot' => $snapshot, 'updates' => ['newGroupName' => 'CSRF group'],
            'calls' => [['path' => '', 'method' => 'createGroup', 'params' => []]],
        ]]];
        $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertStatus(419);
        $this->assertDatabaseCount('tournament_groups', 0);
        $payload['_token'] = 'test-csrf-token';
        $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertOk();
        $this->assertDatabaseHas('tournament_groups', ['name' => 'CSRF group']);
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

    public function test_real_scorer_account_creation_and_revocation_require_csrf(): void
    {
        $this->enforceCsrf();
        config(['tournament.enabled' => true]);
        \Illuminate\Support\Facades\Route::middleware('web')->group(base_path('routes/tournament.php'));
        \Illuminate\Support\Facades\Route::getRoutes()->refreshNameLookups();
        $this->signedIn(User::factory()->create(['is_admin' => true]))
            ->withSession(['_token' => 'test-csrf-token']);
        $html = $this->get('/admin/tournament/scorers')->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $decoded = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (json_decode($decoded, true)['memo']['name'] === 'admin.scorer-accounts-manager') {
                $snapshot = $decoded;
                break;
            }
        }
        $this->assertNotNull($snapshot);
        $payload = ['components' => [[
            'snapshot' => $snapshot, 'updates' => ['name' => 'CSRF scorer', 'email' => 'csrf-scorer@example.com'],
            'calls' => [['path' => '', 'method' => 'createScorer', 'params' => []]],
        ]]];
        $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertStatus(419);
        $this->assertDatabaseMissing('users', ['email' => 'csrf-scorer@example.com']);
        $payload['_token'] = 'test-csrf-token';
        $response = $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertOk();
        $scorer = User::where('email', 'csrf-scorer@example.com')->firstOrFail();
        $this->assertTrue($scorer->is_scorer);
        $payload = ['components' => [[
            'snapshot' => $response->json('components.0.snapshot'), 'updates' => [],
            'calls' => [['path' => '', 'method' => 'revokeScorer', 'params' => [$scorer->id]]],
        ]]];
        $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertStatus(419);
        $this->assertTrue($scorer->fresh()->is_scorer);
        $payload['_token'] = 'test-csrf-token';
        $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true'])->assertOk();
        $this->assertFalse($scorer->fresh()->is_scorer);
    }
}
