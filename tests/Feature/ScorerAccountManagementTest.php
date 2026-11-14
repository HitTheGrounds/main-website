<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\JWTService;
use App\Services\Tournament\ScorerAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ScorerAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['tournament.enabled' => true]);
        // Preserve support for existing admins with company associations.
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => Company::factory()->create()->id]);
        Livewire::withCookie('company_token', (new JWTService)->generateToken($admin));

        return $admin;
    }

    public function test_admin_navigation_exposes_scorer_accounts_only_when_enabled(): void
    {
        $admin = $this->admin();
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $this->signedIn($admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('Scorer accounts')->assertSee(route('admin.tournament.scorers.index'));
        // Covers a registered/cached route whose runtime feature switch is off.
        config(['tournament.enabled' => false]);
        $this->get('/admin/dashboard')->assertOk()->assertDontSee('Scorer accounts');
        $this->get('/admin/tournament/scorers')->assertNotFound();
    }

    public function test_admin_creates_a_separate_unverified_scorer_without_changing_existing_accounts(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $before = $admin->fresh()->getAttributes();
        Volt::test('admin.scorer-accounts-manager')
            ->assertDontSee('wire:model="is_admin"', false)->assertDontSee('wire:model="company_id"', false)
            ->set('name', ' Scorer One ')->set('email', ' Scorer@Example.com ')
            ->call('createScorer')->assertHasNoErrors()->assertSet('name', '')->assertSet('email', '')
            ->assertSee('Scorer account created for scorer@example.com');
        $scorer = User::where('email', 'scorer@example.com')->firstOrFail();
        $this->assertSame('Scorer One', $scorer->name);
        $this->assertTrue($scorer->is_scorer);
        $this->assertFalse($scorer->is_admin);
        foreach (['company_id', 'password', 'email_verified_at', 'jwt_token', 'jwt_expires_at'] as $field) {
            $this->assertNull($scorer->$field);
        }
        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('verification_codes', 0);
        Notification::assertNothingSent();
    }

    public static function existingAccounts(): array
    {
        return [['representative'], ['admin'], ['scorer'], ['pending']];
    }

    #[DataProvider('existingAccounts')]
    public function test_existing_email_is_rejected_without_converting_any_account_type(string $role): void
    {
        $this->admin();
        $existing = User::factory()->create([
            'email' => 'Existing@Example.com', 'is_admin' => $role === 'admin',
            'company_id' => $role === 'representative' ? Company::factory()->create()->id : null,
        ]);
        $existing->is_scorer = $role === 'scorer';
        $existing->save();
        $before = $existing->fresh()->getAttributes();
        Volt::test('admin.scorer-accounts-manager')->set('name', 'Replacement')
            ->set('email', ' existing@example.com ')->call('createScorer')->assertHasErrors(['email'])
            ->assertSet('name', 'Replacement')->assertSet('email', ' existing@example.com ')
            ->assertSee('existing accounts will not be changed');
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 2);
    }

    public static function invalidInputs(): array
    {
        return [['', 'valid@example.com', 'name'], ['Name', 'invalid', 'email'], [str_repeat('x', 256), 'valid@example.com', 'name']];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_fields_do_not_create_accounts(string $name, string $email, string $field): void
    {
        $this->admin();
        Volt::test('admin.scorer-accounts-manager')->set('name', $name)->set('email', $email)
            ->call('createScorer')->assertHasErrors([$field]);
        $this->assertDatabaseCount('users', 1);
    }

    public static function unauthorizedAccounts(): array
    {
        return [['guest'], ['representative'], ['scorer'], ['pending']];
    }

    #[DataProvider('unauthorizedAccounts')]
    public function test_non_admins_cannot_render_the_creation_component(string $role): void
    {
        config(['tournament.enabled' => true]);
        if ($role !== 'guest') {
            $user = User::factory()->create([
                'is_admin' => false,
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null,
            ]);
            $user->is_scorer = $role === 'scorer';
            $user->save();
            Livewire::withCookie('company_token', (new JWTService)->generateToken($user));
        }
        Volt::test('admin.scorer-accounts-manager')->assertForbidden();
        $this->assertDatabaseCount('users', $role === 'guest' ? 0 : 1);
    }

    public function test_direct_creation_rechecks_revoked_admin_permission(): void
    {
        $admin = $this->admin();
        $component = Volt::test('admin.scorer-accounts-manager')->set('name', 'Scorer')->set('email', 'scorer@example.com');
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => false]);
        $component->call('createScorer')->assertForbidden();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_disabled_feature_rejects_creation_after_valid_render(): void
    {
        $this->admin();
        $component = Volt::test('admin.scorer-accounts-manager')->set('name', 'Scorer')->set('email', 'scorer@example.com');
        config(['tournament.enabled' => false]);
        $component->call('createScorer')->assertNotFound();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_creation_throttle_is_per_admin_and_returns_an_inline_error(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('tournament:scorer-account-create:'.$admin->id, 60);
        }
        Volt::test('admin.scorer-accounts-manager')->set('name', 'Scorer')->set('email', 'scorer@example.com')
            ->call('createScorer')->assertHasErrors(['tournament'])->assertSee('Too many account creation attempts');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_created_scorer_uses_the_existing_email_code_login(): void
    {
        Notification::fake();
        $this->admin();
        Volt::test('admin.scorer-accounts-manager')->set('name', 'Scorer')->set('email', 'scorer@example.com')
            ->call('createScorer')->assertHasNoErrors();
        $scorer = User::where('email', 'scorer@example.com')->firstOrFail();
        config(['tournament.enabled' => true]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        Livewire::withCookie('company_token', '');
        $login = Volt::test('company-login-form')->set('contact_email', $scorer->email)->call('sendCode')->assertHasNoErrors();
        $code = VerificationCode::where('user_id', $scorer->id)->firstOrFail();
        $login->set('verification_code', $code->code)->call('verify')->assertHasNoErrors()->assertRedirect('/scorer/dashboard');
        $this->assertTrue($code->fresh()->is_used);
        $this->assertNotNull($scorer->fresh()->email_verified_at);
        $this->assertNotNull($scorer->fresh()->jwt_token);
        $this->assertTrue($scorer->fresh()->is_scorer);
        $this->assertFalse($scorer->fresh()->is_admin);
    }

    private function scorerAccount(string $name = 'Active scorer'): User
    {
        $user = User::factory()->create(['name' => $name, 'company_id' => null, 'is_admin' => false]);
        $user->is_scorer = true;
        $user->save();

        return $user;
    }

    public function test_list_is_paginated_and_contains_only_dedicated_active_scorers(): void
    {
        $admin = $this->admin();
        request()->attributes->set('user', $admin);
        $first = $this->scorerAccount('Oldest active scorer');
        for ($i = 0; $i < 9; $i++) {
            $this->scorerAccount('Other scorer '.$i);
        }
        $this->scorerAccount('Newest active scorer');
        User::factory()->create(['name' => 'Pending account', 'company_id' => null]);
        $company = $this->scorerAccount('Company account');
        $company->company_id = Company::factory()->create()->id;
        $company->save();
        $listing = app(ScorerAccountService::class)->listing();
        $this->assertSame(11, $listing->total());
        $this->assertCount(10, $listing->items());
        foreach ($listing->items() as $record) {
            $this->assertArrayNotHasKey('jwt_token', $record->getAttributes());
            $this->assertArrayNotHasKey('password', $record->getAttributes());
        }
        Volt::test('admin.scorer-accounts-manager')->assertSee('Newest active scorer')
            ->assertDontSee('Oldest active scorer')->assertDontSee('Pending account')->assertDontSee('Company account')
            ->call('gotoPage', 2)->assertSee($first->name);
    }

    public function test_revocation_preserves_account_token_and_recorded_history(): void
    {
        $this->admin();
        $target = $this->scorerAccount();
        $token = (new JWTService)->generateToken($target);
        $teams = \App\Models\Team::factory()->count(2)->create();
        $matchId = DB::table('matches')->insertGetId([
            'tournament_year' => 2026, 'stage' => 'QF', 'bracket_position' => 1,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4,
            'created_by' => $target->id,
        ]);
        $history = (array) DB::table('matches')->where('id', $matchId)->first();
        $before = $target->fresh()->getAttributes();
        Volt::test('admin.scorer-accounts-manager')->call('revokeScorer', $target->id)
            ->assertHasNoErrors()->assertSee('Scorer access removed.')->assertSee('No active scorer accounts.');
        $after = $target->fresh()->getAttributes();
        $this->assertFalse($target->fresh()->is_scorer);
        unset($before['is_scorer'], $before['updated_at'], $after['is_scorer'], $after['updated_at']);
        $this->assertSame($before, $after);
        $this->assertSame($history, (array) DB::table('matches')->where('id', $matchId)->first());
        $this->assertSame($token, $target->fresh()->jwt_token);
        $this->assertNotNull((new JWTService)->getUserFromToken($token));
    }

    public static function invalidRevocationTargets(): array
    {
        return [['admin'], ['company'], ['pending'], ['revoked'], ['missing']];
    }

    #[DataProvider('invalidRevocationTargets')]
    public function test_revocation_cannot_edit_unrelated_or_already_revoked_accounts(string $type): void
    {
        $this->admin();
        $target = $type === 'missing' ? null : $this->scorerAccount();
        if ($target) {
            $target->is_admin = $type === 'admin';
            $target->company_id = $type === 'company' ? Company::factory()->create()->id : null;
            $target->is_scorer = ! in_array($type, ['pending', 'revoked'], true);
            $target->save();
        }
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        Volt::test('admin.scorer-accounts-manager')->call('revokeScorer', $target?->id ?? 999999)
            ->assertHasErrors(['tournament'])->assertSee('Select an active dedicated scorer account.');
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_revoked_admin_cannot_call_revoke_directly_after_rendering(): void
    {
        $admin = $this->admin();
        $target = $this->scorerAccount();
        $component = Volt::test('admin.scorer-accounts-manager');
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => false]);
        $component->call('revokeScorer', $target->id)->assertForbidden();
        $this->assertTrue($target->fresh()->is_scorer);
    }

    #[DataProvider('unauthorizedAccounts')]
    public function test_non_admins_cannot_call_listing_or_revocation_services(string $role): void
    {
        config(['tournament.enabled' => true]);
        $target = $this->scorerAccount();
        $actor = $role === 'guest' ? null : User::factory()->create([
            'company_id' => $role === 'representative' ? Company::factory()->create()->id : null,
        ]);
        if ($actor && $role === 'scorer') {
            $actor->is_scorer = true;
            $actor->save();
        }
        request()->attributes->set('user', $actor);
        foreach ([fn () => app(ScorerAccountService::class)->listing(), fn () => app(ScorerAccountService::class)->revoke($target->id)] as $call) {
            try {
                $call();
                $this->fail('Expected admin-only service rejection.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertTrue($target->fresh()->is_scorer);
    }

    public function test_disabled_feature_blocks_revocation_without_changing_target(): void
    {
        $this->admin();
        $target = $this->scorerAccount();
        $component = Volt::test('admin.scorer-accounts-manager');
        config(['tournament.enabled' => false]);
        $component->call('revokeScorer', $target->id)->assertNotFound();
        $this->assertTrue($target->fresh()->is_scorer);
    }

    public function test_revocation_throttle_keeps_target_access_unchanged(): void
    {
        $admin = $this->admin();
        $target = $this->scorerAccount();
        for ($i = 0; $i < 30; $i++) {
            RateLimiter::hit('tournament:scorer-account-revoke:'.$admin->id, 60);
        }
        Volt::test('admin.scorer-accounts-manager')->call('revokeScorer', $target->id)
            ->assertHasErrors(['tournament'])->assertSee('Too many access changes');
        $this->assertTrue($target->fresh()->is_scorer);
    }

    public function test_real_admin_revocation_blocks_a_scorers_previously_open_group_editor(): void
    {
        $admin = $this->admin();
        $target = $this->scorerAccount();
        $token = (new JWTService)->generateToken($target);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $html = $this->withCookie('company_token', $token)->get('/scorer/groups')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $groupSnapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = $this->withCookie('company_token', $admin->fresh()->jwt_token)->get('/admin/tournament/scorers')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $adminSnapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->withCredentials()->postJson('/livewire/update', ['components' => [[
            'snapshot' => $adminSnapshot, 'updates' => [],
            'calls' => [['path' => '', 'method' => 'revokeScorer', 'params' => [$target->id]]],
        ]]], ['X-Livewire' => 'true'])->assertOk();
        $this->assertFalse($target->fresh()->is_scorer);
        $this->withCookie('company_token', $token)->withCredentials()->postJson('/livewire/update', ['components' => [[
            'snapshot' => $groupSnapshot, 'updates' => ['newGroupName' => 'Unauthorized'],
            'calls' => [['path' => '', 'method' => 'createGroup', 'params' => []]],
        ]]], ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertDatabaseCount('tournament_groups', 0);
        $this->assertSame($token, $target->fresh()->jwt_token);
    }

    public function test_real_admin_page_can_create_and_then_rejects_a_revoked_admin_snapshot(): void
    {
        $admin = $this->admin();
        $this->signedIn($admin);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $html = $this->get('/admin/tournament/scorers')->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $decoded = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (json_decode($decoded, true)['memo']['name'] === 'admin.scorer-accounts-manager') {
                $snapshot = $decoded;
            }
        }
        $this->assertNotNull($snapshot);
        $update = function (string $snapshot, string $email) {
            return $this->withCredentials()->postJson('/livewire/update', ['components' => [[
                'snapshot' => $snapshot, 'updates' => ['name' => 'HTTP Scorer', 'email' => $email],
                'calls' => [['path' => '', 'method' => 'createScorer', 'params' => []]],
            ]]], ['X-Livewire' => 'true']);
        };
        $response = $update($snapshot, 'http-scorer@example.com')->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'http-scorer@example.com', 'is_scorer' => true, 'is_admin' => false, 'company_id' => null]);
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => false]);
        $update($response->json('components.0.snapshot'), 'blocked@example.com')->assertForbidden();
        $target = User::where('email', 'http-scorer@example.com')->firstOrFail();
        $this->withCredentials()->postJson('/livewire/update', ['components' => [[
            'snapshot' => $response->json('components.0.snapshot'), 'updates' => [],
            'calls' => [['path' => '', 'method' => 'revokeScorer', 'params' => [$target->id]]],
        ]]], ['X-Livewire' => 'true'])->assertForbidden();
        $this->assertTrue($target->fresh()->is_scorer);
        $this->assertDatabaseCount('users', 2);
    }

    #[DataProvider('unauthorizedAccounts')]
    public function test_non_admins_cannot_replay_a_valid_admin_snapshot_for_account_writes(string $role): void
    {
        $admin = $this->admin();
        $target = $this->scorerAccount();
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $html = $this->signedIn($admin)->get('/admin/tournament/scorers')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $token = '';
        if ($role !== 'guest') {
            $user = User::factory()->create([
                'is_admin' => false,
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null,
            ]);
            $user->is_scorer = $role === 'scorer';
            $user->save();
            $token = (new JWTService)->generateToken($user);
        }
        $before = $target->fresh()->getAttributes();
        $count = User::count();
        foreach (['createScorer', 'revokeScorer'] as $action) {
            $response = $this->withCookie('company_token', $token)->withCredentials()->postJson('/livewire/update', ['components' => [[
                'snapshot' => $snapshot,
                'updates' => ['name' => 'Unauthorized', 'email' => 'unauthorized@example.com'],
                'calls' => [['path' => '', 'method' => $action, 'params' => $action === 'revokeScorer' ? [$target->id] : []]],
            ]]], ['X-Livewire' => 'true']);
            if ($role === 'guest') {
                $response->assertRedirect(route('login'));
            } else {
                $response->assertForbidden();
            }
        }
        $this->assertSame($count, User::count());
        $this->assertSame($before, $target->fresh()->getAttributes());
    }
}
