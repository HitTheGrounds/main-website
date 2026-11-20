<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ScorerGroundsScreenTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tournament.enabled' => true,
            'tournament.year' => 2026,
            'tournament.venues' => ['Ground 1', 'Ground 2', 'Ground 3', 'Ground 4'],
        ]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_screen_requires_tournament_enabled(): void
    {
        config(['tournament.enabled' => false]);

        $scorer = User::factory()->create(['is_admin' => false, 'company_id' => null, 'is_scorer' => true]);

        $this->signedIn($scorer)->get('/scorer/screen/grounds')->assertNotFound();
    }

    public function test_screen_redirects_unauthenticated_guests(): void
    {
        $this->get('/scorer/screen/grounds')->assertRedirect(route('login'));
    }

    public function test_screen_forbids_non_scorer_users(): void
    {
        $company = User::factory()->create(['is_admin' => false, 'is_scorer' => false]);
        $this->signedIn($company)->get('/scorer/screen/grounds')->assertForbidden();
    }

    public function test_local_screen_does_not_invent_fixtures_when_no_matches_exist(): void
    {
        $scorer = User::factory()->create(['is_admin' => false, 'company_id' => null, 'is_scorer' => true]);
        $this->app->instance('env', 'local');

        try {
            $this->signedIn($scorer)->get('/scorer/screen/grounds')->assertOk()
                ->assertSee('Ground available')
                ->assertSee('No upcoming matches')
                ->assertDontSee('Next match:')
                ->assertDontSee('DEMO 2026')
                ->assertDontSee('led-sched-item-matchup');
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_screen_renders_four_grounds_and_matches_without_navbars(): void
    {
        $team1 = Team::factory()->create(['team_name' => 'Alpha Titans', 'approved' => true]);
        $team2 = Team::factory()->create(['team_name' => 'Beta Warriors', 'approved' => true]);
        $team3 = Team::factory()->create(['team_name' => 'Gamma Strikers', 'approved' => true]);
        $team4 = Team::factory()->create(['team_name' => 'Delta Knights', 'approved' => true]);

        $groupId = DB::table('tournament_groups')->insertGetId([
            'tournament_year' => 2026,
            'name' => 'Group A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ground 1 has an in-progress match and an upcoming match
        DB::table('matches')->insert([
            'tournament_year' => 2026,
            'group_id' => $groupId,
            'stage' => 'G',
            'team1_id' => $team1->id,
            'team2_id' => $team2->id,
            'display_number' => 1,
            'balls_per_over' => 4,
            'overs_per_innings' => 5,
            'max_wickets' => 7,
            'status' => 'live',
            'batting_first_id' => $team1->id,
            'venue' => 'Ground 1',
            'scheduled_at' => CarbonImmutable::now()->subMinutes(10)->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('matches')->insert([
            'tournament_year' => 2026,
            'group_id' => $groupId,
            'stage' => 'G',
            'team1_id' => $team3->id,
            'team2_id' => $team4->id,
            'display_number' => 5,
            'balls_per_over' => 4,
            'overs_per_innings' => 5,
            'max_wickets' => 7,
            'status' => 'upcoming',
            'venue' => 'Ground 1',
            'scheduled_at' => CarbonImmutable::now()->addHour()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Ground 2 has an upcoming match
        DB::table('matches')->insert([
            'tournament_year' => 2026,
            'group_id' => $groupId,
            'stage' => 'G',
            'team1_id' => $team2->id,
            'team2_id' => $team3->id,
            'display_number' => 2,
            'balls_per_over' => 4,
            'overs_per_innings' => 5,
            'max_wickets' => 7,
            'status' => 'upcoming',
            'venue' => 'Ground 2',
            'scheduled_at' => CarbonImmutable::now()->addMinutes(30)->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scorer = User::factory()->create(['is_admin' => false, 'company_id' => null, 'is_scorer' => true]);

        $response = $this->signedIn($scorer)->get('/scorer/screen/grounds');

        $response->assertOk()
            // The four grounds are displayed
            ->assertSee('Ground 1')
            ->assertSee('Ground 2')
            ->assertSee('Ground 3')
            ->assertSee('Ground 4')
            ->assertSee('G1')
            ->assertSee('G2')
            ->assertSee('G3')
            ->assertSee('G4')
            // In-progress match on Ground 1
            ->assertSee('In progress: Alpha Titans versus Beta Warriors')
            ->assertSee('Alpha Titans')
            ->assertSee('Beta Warriors')
            // Upcoming match on Ground 1
            ->assertSee('Gamma Strikers')
            ->assertSee('Delta Knights')
            // Ground 2 upcoming
            ->assertSee('Next match: Beta Warriors versus Gamma Strikers')
            // Ground 4 has no matches
            ->assertSee('Ground available')
            // 2:1 ratio LED viewport and stage
            ->assertSee('led-viewport')
            ->assertSee('led-grounds-stage')
            ->assertSee('led-grounds-grid')
            // No scorer or public navigation bars
            ->assertDontSee('scorer-rail')
            ->assertDontSee('scorer-nav')
            ->assertDontSee('Skip to scorer tools')
            // Revision check script
            ->assertSee('tournament\/rev', false);
    }
}
