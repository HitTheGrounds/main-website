<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TournamentMutationSecurityTest extends TestCase
{
    use DatabaseMigrations;

    public static function screens(): array
    {
        return [
            'groups' => ['/scorer/groups', 'scorer.groups-manager', [
                ['createGroup', []], ['renameGroup', []], ['assignTeam', []], ['removeTeam', [1]], ['deleteGroup', []],
            ]],
            'schedule' => ['/scorer/dashboard', 'scorer.matches-manager', [
                ['createMatch', []], ['saveSchedule', []], ['changeResultStatus', ['live']],
            ]],
            'results' => ['/scorer/matches/1/result', 'scorer.result-editor', [
                ['saveDraft', []], ['reviewResult', []], ['publishResult', []], ['discardDraft', []],
            ]],
            'players' => ['/scorer/matches/1/players', 'scorer.player-editor', [
                ['savePlayer', []], ['discardDraft', []], ['stageWithdrawal', []], ['publishPlayerUpdates', []],
            ]],
            'standings' => ['/scorer/standings', 'scorer.standings-manager', [['saveAdjustment', []]]],
            'knockouts' => ['/scorer/knockouts', 'scorer.knockouts-manager', [
                ['saveSelection', []], ['createMatch', []], ['moveMatch', []],
            ]],
            'accounts' => ['/admin/tournament/scorers', 'admin.scorer-accounts-manager', [
                ['createScorer', []], ['revokeScorer', [1]],
            ]],
        ];
    }

    public static function deniedScreens(): array
    {
        $cases = [];
        foreach (self::screens() as $name => $screen) {
            foreach (['guest', 'opposite role', 'representative', 'revoked', 'disabled'] as $role) {
                $cases[$name.' / '.$role] = [...$screen, $role];
            }
        }

        return $cases;
    }

    private function fixture(string $path, string $component): array
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        // Laravel normally skips CSRF during tests; exercise its real checks here.
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $admin = str_starts_with($path, '/admin/');
        $actor = User::factory()->create(['is_admin' => $admin, 'company_id' => null]);
        $actor->is_scorer = ! $admin;
        $actor->save();
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'Security group', 'tournament_year' => 2026]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'team_id' => $team->id, 'tournament_year' => 2026]);
        }
        $match = DB::table('matches')->insertGetId(['group_id' => $group, 'stage' => 'G', 'tournament_year' => 2026,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);
        $path = str_replace('/matches/1/', '/matches/'.$match.'/', $path);
        $this->signedIn($actor)->withSession(['_token' => 'tournament-security-csrf']);
        $html = $this->get($path)->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = null;
        foreach ($matches[1] as $encoded) {
            $decoded = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (json_decode($decoded, true, flags: JSON_THROW_ON_ERROR)['memo']['name'] === $component) {
                $snapshot = $decoded;
            }
        }
        $this->assertNotNull($snapshot);
        // Positive control: this signed snapshot/session reaches the real update
        // endpoint with valid CSRF, before testing any forbidden action.
        $this->update($snapshot, [], 'tournament-security-csrf')->assertOk();

        return [$actor, $snapshot];
    }

    private function update(string $snapshot, array $calls, ?string $token)
    {
        $payload = ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => $calls]]];
        if ($token !== null) {
            $payload['_token'] = $token;
        }

        return $this->withCredentials()->postJson('/livewire/update', $payload, ['X-Livewire' => 'true']);
    }

    private function state(): array
    {
        $state = [];
        foreach (['users', 'teams', 'team_members', 'tournament_groups', 'group_teams',
            'matches', 'player_match_stats', 'player_tournament_stats'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }

    #[DataProvider('screens')]
    public function test_every_write_rejects_missing_and_wrong_csrf_without_changing_records(string $path, string $component, array $actions): void
    {
        [$actor, $snapshot] = $this->fixture($path, $component);
        $before = $this->state();
        foreach ($actions as [$method, $params]) {
            $calls = [['path' => '', 'method' => $method, 'params' => $params]];
            foreach ([null, 'wrong-csrf-token'] as $token) {
                $this->update($snapshot, $calls, $token)->assertStatus(419)
                    ->assertDontSee($actor->fresh()->jwt_token, false)->assertDontSee('"snapshot":', false);
                $this->assertSame($before, $this->state(), $component.'::'.$method);
            }
        }
    }

    #[DataProvider('deniedScreens')]
    public function test_every_write_rechecks_access_on_a_real_signed_snapshot(string $path, string $component, array $actions, string $role): void
    {
        [$actor, $snapshot] = $this->fixture($path, $component);
        $expected = 403;
        if ($role === 'guest') {
            $this->withCookie('company_token', '');
            $expected = 302;
        } elseif ($role === 'disabled') {
            config(['tournament.enabled' => false]);
            $expected = 404;
        } elseif ($role === 'revoked') {
            DB::table('users')->where('id', $actor->id)->update(['is_admin' => false, 'is_scorer' => false]);
        } else {
            $replacement = User::factory()->create(['is_admin' => $role === 'opposite role' && ! $actor->is_admin,
                'company_id' => $role === 'representative' ? Company::factory()->create()->id : null]);
            $replacement->is_scorer = $role === 'opposite role' && $actor->is_admin;
            $replacement->save();
            $this->signedIn($replacement);
        }
        $before = $this->state();
        foreach ($actions as [$method, $params]) {
            $response = $this->update($snapshot, [['path' => '', 'method' => $method, 'params' => $params]], 'tournament-security-csrf')
                ->assertStatus($expected)->assertDontSee($actor->fresh()->jwt_token, false)->assertDontSee('"snapshot":', false);
            if ($role === 'guest') {
                $response->assertRedirect(route('login'));
            }
            $this->assertSame($before, $this->state(), $component.'::'.$method.' / '.$role);
        }
    }
}
