<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegacyTournamentCompatibilityTest extends TestCase
{
    use DatabaseMigrations;

    public static function featureStates(): array
    {
        return ['disabled' => [false], 'enabled' => [true]];
    }

    #[DataProvider('featureStates')]
    public function test_registration_login_team_management_and_uploads_keep_existing_behaviour(bool $enabled): void
    {
        config(['tournament.enabled' => $enabled, 'tournament.year' => 2026,
            'registration.enabled' => true, 'registration.team_enabled' => true]);
        if ($enabled) {
            Route::middleware('web')->group(base_path('routes/tournament.php'));
            Route::getRoutes()->refreshNameLookups();
        }
        Notification::fake();
        Http::preventStrayRequests();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        // Both persistent uploads and Livewire temporary files stay on fake disks.
        Storage::fake('public');
        Storage::fake('local');
        config(['livewire.temporary_file_upload.disk' => 'local']);
        $registration = Volt::test('company-registration-form')->set('company_name', 'Legacy organization')
            ->set('contact_person_name', 'Legacy representative')->set('contact_email', 'legacy@example.test')
            ->set('contact_person_phone', '0771234567')->set('turnstile_token', 'test-token')
            ->call('register')->assertHasNoErrors();
        $representative = User::where('email', 'legacy@example.test')->firstOrFail();
        $code = VerificationCode::where('user_id', $representative->id)->firstOrFail();
        $registration->set('verification_code', $code->code)->call('verify')
            ->assertHasNoErrors()->assertRedirect('/company/dashboard');
        $this->assertTrue($code->fresh()->is_used);
        $this->assertFalse($representative->fresh()->is_scorer);
        $this->assertFalse($representative->fresh()->is_admin);
        $this->useIssuedSession($representative);
        $this->get('/company/dashboard')->assertOk();
        $this->get('/admin/dashboard')->assertForbidden();

        $company = $representative->company;
        Volt::test('company.edit-company-profile', ['company' => $company])
            ->set('logo', UploadedFile::fake()->image('legacy-logo.png'))
            ->call('updateCompany')->assertHasNoErrors();
        $logo = $company->fresh()->logo;
        Storage::disk('public')->assertExists($logo);
        $logoHash = hash('sha256', Storage::disk('public')->get($logo));
        Volt::test('company.team-form')->set('team_name', 'Legacy team')
            ->set('captain_email', 'captain@example.test')->set('captain_phone', '0771234567')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('company.teams'));
        $team = Team::where('user_id', $representative->id)->firstOrFail();
        $this->assertFalse($team->approved);
        $this->assertSame($company->id, $team->company_id);
        Volt::test('company.team-members', ['team' => $team])->set('member_name', 'Legacy player')
            ->set('gender', 'Female')->set('photo', UploadedFile::fake()->image('legacy-player.png'))
            ->call('saveMember')->assertHasNoErrors();
        $member = $team->members()->firstOrFail();
        $photo = $member->picture;
        Storage::disk('public')->assertExists($photo);
        $photoHash = hash('sha256', Storage::disk('public')->get($photo));

        // Existing admins still use email-code login and approve ordinary teams.
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => null]);
        Livewire::withCookie('company_token', '');
        $login = Volt::test('company-login-form')->set('contact_email', $admin->email)
            ->call('sendCode')->assertHasNoErrors();
        $code = VerificationCode::where('user_id', $admin->id)->firstOrFail();
        $login->set('verification_code', $code->code)->call('verify')
            ->assertHasNoErrors()->assertRedirect('/admin/dashboard');
        $this->useIssuedSession($admin);
        $this->get('/admin/dashboard')->assertOk();
        Volt::test('admin.approve-team', ['team' => $team])->call('approve')->assertHasNoErrors();
        $this->assertTrue($team->fresh()->approved);
        $this->assertFalse($admin->fresh()->is_scorer);

        $this->useIssuedSession($representative);
        $this->get('/company/dashboard')->assertOk();
        $this->get('/admin/dashboard')->assertForbidden();
        $this->withCookie('company_token', '');
        foreach (['/', '/about', '/memories', '/partners'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->assertSame($logoHash, hash('sha256', Storage::disk('public')->get($logo)));
        $this->assertSame($photoHash, hash('sha256', Storage::disk('public')->get($photo)));
        $this->assertSame('Legacy player', $member->fresh()->name);
        $this->assertSame($representative->id, $team->fresh()->user_id);
        foreach (['tournament_groups', 'group_teams', 'matches', 'player_match_stats', 'player_tournament_stats'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    private function useIssuedSession(User $user): void
    {
        // Share the existing login token across clients; do not rotate it here.
        $token = $user->fresh()->jwt_token;
        $this->assertNotNull($token);
        $this->withCookie('company_token', $token);
        Livewire::withCookie('company_token', $token);
    }
}
