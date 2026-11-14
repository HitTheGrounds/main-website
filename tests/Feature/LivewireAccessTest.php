<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTournamentEnabled;
use App\Models\Company;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LivewireAccessTest extends TestCase
{
    use RefreshDatabase;

    private function snapshot(string $path, string $name): string
    {
        $html = $this->get($path)->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (json_decode($snapshot, true)['memo']['name'] === $name) {
                return $snapshot;
            }
        }
        $this->fail('Expected component snapshot was not rendered.');
    }

    private function update(string $snapshot, ?string $method = null, array $params = [], array $updates = []): TestResponse
    {
        // Browser Livewire requests carry same-origin cookies; Laravel's JSON
        // test helper omits them unless credentials are explicitly enabled.
        return $this->withCredentials()->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot, 'updates' => $updates,
            'calls' => $method === null ? [] : [['path' => '', 'method' => $method, 'params' => $params]],
        ]]], ['X-Livewire' => 'true']);
    }

    public function test_company_snapshot_is_rejected_after_owner_becomes_a_scorer(): void
    {
        $owner = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $team = Team::factory()->create(['user_id' => $owner->id, 'company_id' => $owner->company_id]);
        $this->signedIn($owner);
        $snapshot = $this->snapshot(route('company.teams.show', $team), 'company.team-members');
        DB::table('users')->where('id', $owner->id)->update(['is_scorer' => true]);
        $this->update($snapshot)->assertForbidden();
        $this->update($snapshot, 'saveMember', updates: ['member_name' => 'Injected player', 'gender' => 'Male'])->assertForbidden();
        $this->assertSame(0, $team->members()->count());
    }

    public function test_admin_update_rechecks_target_scorer_flag_after_page_was_opened(): void
    {
        $target = User::factory()->create(['company_id' => null, 'is_admin' => false]);
        $this->signedIn(User::factory()->create(['is_admin' => true]));
        $snapshot = $this->snapshot('/admin/users', 'admin.users-list');
        DB::table('users')->where('id', $target->id)->update(['is_scorer' => true]);
        $before = $target->fresh()->getAttributes();
        $this->update($snapshot, 'promoteToAdmin', [$target->id])->assertOk();
        $this->assertSame($before, $target->fresh()->getAttributes());
    }

    public function test_current_admin_can_mutate_through_the_real_update_endpoint(): void
    {
        $team = Team::factory()->create(['approved' => false]);
        $this->signedIn(User::factory()->create(['is_admin' => true]));
        $snapshot = $this->snapshot('/admin/teams', 'admin.teams-list');
        $this->update($snapshot, 'approveTeam', [$team->id])->assertOk();
        $this->assertTrue($team->fresh()->approved);
    }

    public function test_revoked_admin_cannot_use_a_previously_valid_snapshot(): void
    {
        $team = Team::factory()->create(['approved' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->signedIn($admin);
        $snapshot = $this->snapshot('/admin/teams', 'admin.teams-list');
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => false]);
        $this->update($snapshot)->assertForbidden();
        $this->update($snapshot, 'approveTeam', [$team->id])->assertForbidden();
        $this->assertFalse($team->fresh()->approved);
    }

    public function test_revoked_jwt_cannot_use_a_previously_valid_snapshot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->signedIn($admin);
        $snapshot = $this->snapshot('/admin/teams', 'admin.teams-list');
        (new JWTService)->revokeToken($admin);
        $this->update($snapshot)->assertRedirect(route('login'));
    }

    public function test_owner_change_is_checked_again_during_a_real_member_update(): void
    {
        $owner = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $team = Team::factory()->create(['user_id' => $owner->id, 'company_id' => $owner->company_id]);
        $this->signedIn($owner);
        $snapshot = $this->snapshot(route('company.teams.show', $team), 'company.team-members');
        $replacement = User::factory()->create(['company_id' => $owner->company_id]);
        DB::table('teams')->where('id', $team->id)->update(['user_id' => $replacement->id]);
        $this->update($snapshot, 'saveMember', updates: ['member_name' => 'Injected player', 'gender' => 'Male'])->assertForbidden();
        $this->assertSame(0, $team->members()->count());
    }

    private function scorerProbe(): User
    {
        config(['tournament.enabled' => true]);
        PersistentScorerProbe::$calls = 0;
        Livewire::component('scorer-access-probe', PersistentScorerProbe::class);
        Route::get('/_test/scorer-livewire', fn () => Blade::render('<livewire:scorer-access-probe />'))
            ->middleware(['web', EnsureTournamentEnabled::class, 'auth.company', 'scorer']);
        $scorer = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $scorer->is_scorer = true;
        $scorer->save();
        $this->signedIn($scorer);

        return $scorer;
    }

    public function test_scorer_revocation_is_enforced_on_actual_livewire_updates(): void
    {
        $scorer = $this->scorerProbe();
        $snapshot = $this->snapshot('/_test/scorer-livewire', 'scorer-access-probe');
        $this->update($snapshot, 'ping')->assertOk();
        $this->assertSame(1, PersistentScorerProbe::$calls);
        DB::table('users')->where('id', $scorer->id)->update(['is_scorer' => false]);
        $this->update($snapshot, 'ping')->assertForbidden();
        $this->assertSame(1, PersistentScorerProbe::$calls);
    }

    public function test_scorer_write_limit_blocks_mutation_on_real_livewire_requests(): void
    {
        $this->scorerProbe();
        config(['tournament.writes_per_minute' => 2]);
        $snapshot = $this->snapshot('/_test/scorer-livewire', 'scorer-access-probe');
        $this->update($snapshot, 'writePing')->assertOk();
        $this->update($snapshot)->assertOk();
        $this->update($snapshot, 'writePing')->assertOk();
        $response = $this->update($snapshot, 'writePing')->assertOk();
        $this->assertStringContainsString('Too many changes', $response->json('components.0.effects.html'));
        $this->assertSame(2, PersistentScorerProbe::$calls);
    }

    public function test_revoked_scorer_is_denied_before_write_throttle_even_with_valid_jwt(): void
    {
        $scorer = $this->scorerProbe();
        config(['tournament.writes_per_minute' => 1]);
        $snapshot = $this->snapshot('/_test/scorer-livewire', 'scorer-access-probe');
        $this->update($snapshot, 'writePing')->assertOk();
        DB::table('users')->where('id', $scorer->id)->update(['is_scorer' => false]);
        $this->assertNotNull((new JWTService)->getUserFromToken($scorer->fresh()->jwt_token));
        $this->update($snapshot, 'writePing')->assertForbidden();
        $this->assertSame(1, PersistentScorerProbe::$calls);
    }

    public function test_revoked_scorer_token_cannot_reuse_a_previously_valid_snapshot(): void
    {
        $scorer = $this->scorerProbe();
        $snapshot = $this->snapshot('/_test/scorer-livewire', 'scorer-access-probe');
        (new JWTService)->revokeToken($scorer);
        $this->update($snapshot, 'writePing')->assertRedirect(route('login'));
        $this->assertSame(0, PersistentScorerProbe::$calls);
    }

    public function test_expired_scorer_session_cannot_reuse_a_previously_valid_snapshot(): void
    {
        $scorer = $this->scorerProbe();
        $snapshot = $this->snapshot('/_test/scorer-livewire', 'scorer-access-probe');
        DB::table('users')->where('id', $scorer->id)->update(['jwt_expires_at' => now()->subMinute()]);
        $this->update($snapshot, 'writePing')->assertRedirect(route('login'));
        $this->assertSame(0, PersistentScorerProbe::$calls);
    }

    public function test_disabled_feature_blocks_a_previously_valid_scorer_update(): void
    {
        $this->scorerProbe();
        $snapshot = $this->snapshot('/_test/scorer-livewire', 'scorer-access-probe');
        config(['tournament.enabled' => false]);
        $this->update($snapshot, 'ping')->assertNotFound();
        $this->assertSame(0, PersistentScorerProbe::$calls);
    }

    public function test_guest_login_and_registration_components_can_still_update(): void
    {
        foreach (['/login' => 'company-login-form', '/register' => 'company-registration-form'] as $path => $name) {
            $this->update($this->snapshot($path, $name))->assertOk();
        }
    }

    public static function nonAdminActors(): array
    {
        return ['guest' => ['guest'], 'representative' => ['representative'], 'pending' => ['pending'], 'scorer' => ['scorer']];
    }

    #[DataProvider('nonAdminActors')]
    public function test_direct_admin_mutations_reject_non_admins(string $role): void
    {
        $team = Team::factory()->create(['approved' => false, 'locked' => false]);
        $member = TeamMember::factory()->create(['team_id' => $team->id]);
        $company = Company::factory()->create(['max_team_count' => 2]);
        $target = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        if ($role !== 'guest') {
            $actor = User::factory()->create(['is_admin' => false, 'company_id' => $role === 'representative' ? $company->id : null]);
            $actor->is_scorer = $role === 'scorer';
            $actor->save();
            Livewire::withCookie('company_token', (new JWTService)->generateToken($actor));
        }
        foreach ([
            ['admin.teams-list', [], 'approveTeam', [$team->id]],
            ['admin.teams-list', [], 'toggleLock', [$team->id]],
            ['admin.companies-list', [], 'updateMaxTeamCount', [$company->id]],
            ['admin.edit-team', ['team' => $team], 'update', []],
            ['admin.users-list', [], 'promoteToAdmin', [$target->id]],
            ['admin.team-members-manager', ['team' => $team], 'saveMember', []],
            ['admin.team-members-manager', ['team' => $team], 'openEditModal', [$member->id]],
        ] as [$component, $properties, $method, $params]) {
            Volt::test($component, $properties)->call($method, ...$params)->assertForbidden();
        }
        $this->assertFalse($team->fresh()->approved);
        $this->assertFalse($team->fresh()->locked);
        $this->assertFalse($target->fresh()->is_admin);
        $this->assertSame(2, $company->fresh()->max_team_count);
        $this->assertSame(1, $team->members()->count());
    }

    public function test_other_owner_cannot_save_roster_or_company_details(): void
    {
        $actor = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $team = Team::factory()->create();
        Livewire::withCookie('company_token', (new JWTService)->generateToken($actor));
        Volt::test('company.team-members', ['team' => $team])->call('saveMember')->assertForbidden();
        Volt::test('company.team-members', ['team' => $team])->call('openAddModal')->assertForbidden();
        Volt::test('company.edit-company-profile', ['company' => $team->company])->call('updateCompany')->assertForbidden();
        Volt::test('company.edit-company-profile', ['company' => $team->company])->call('updateDescription')->assertForbidden();
        $this->assertSame(0, $team->members()->count());
    }

    public function test_existing_member_lookup_does_not_return_another_teams_player(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $foreign = TeamMember::factory()->create();
        Livewire::withCookie('company_token', (new JWTService)->generateToken($owner));
        Volt::test('company.team-members', ['team' => $team])->set('editingId', $foreign->id)
            ->call('getExistingMemberProperty')->assertReturned(null);
    }

    public function test_logo_replacement_uses_the_authorized_database_path(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company-logos/own.jpg', 'own');
        Storage::disk('public')->put('company-logos/other.jpg', 'other');
        $company = Company::factory()->create(['logo' => 'company-logos/own.jpg']);
        $owner = User::factory()->create(['company_id' => $company->id]);
        Livewire::withCookie('company_token', (new JWTService)->generateToken($owner));
        Volt::test('company.edit-company-profile', ['company' => $company])
            ->set('existingLogo', 'company-logos/other.jpg')->set('logo', UploadedFile::fake()->image('new.jpg'))
            ->call('updateCompany')->assertHasNoErrors();
        Storage::disk('public')->assertExists('company-logos/other.jpg');
        Storage::disk('public')->assertMissing('company-logos/own.jpg');
        Storage::disk('public')->assertExists($company->fresh()->logo);
    }
}

class PersistentScorerProbe extends Component
{
    public static int $calls = 0;

    public function ping(): void
    {
        self::$calls++;
    }

    public function writePing(): void
    {
        \App\Support\ScorerWriteThrottle::hit();
        self::$calls++;
    }

    public function render(): string
    {
        return '<div>Scorer access probe '.e($this->getErrorBag()->first('tournament')).'</div>';
    }
}
