<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Services\Tournament\TournamentSummaryRebuildService;
use Database\Seeders\TournamentDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TournamentDemoSeederTest extends TestCase
{
    use DatabaseMigrations;

    private function populate(): void
    {
        config(['tournament.year' => 2026]);
        $this->seed(TournamentDemoSeeder::class);
    }

    private function refuse(): void
    {
        try {
            $this->populate();
            $this->fail('Unsafe seeding should have been refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No demo data added', $exception->getMessage());
        }
    }

    public function test_demo_populates_rankings_and_preserves_existing_registration_records(): void
    {
        $existing = Team::factory()->create();
        $before = $existing->fresh()->getAttributes();
        $this->populate();
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('teams', 29);
        $this->assertDatabaseCount('team_members', 336);
        $this->assertDatabaseCount('matches', 42);
        $this->assertSame(35, DB::table('matches')->whereNotNull('result_published_at')->count());
        $this->assertSame(224, DB::table('player_match_stats')->where('record_state', 'published')->count());
        $this->assertSame(14, DB::table('player_match_stats')->where('record_state', 'draft')->count());
        $this->assertSame(112, DB::table('player_tournament_stats')->count());
        $this->assertSame(24, (int) DB::table('player_tournament_stats')->max('highest_score'));
        $this->assertSame(0, DB::table('users')->where('is_scorer', true)->count());
        $this->assertSame(0, DB::table('users')->where('is_admin', true)->count());
        $this->assertSame(0, DB::table('group_teams')->where('played', 0)->count());

        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $this->get('/tournament?tab=stats')->assertOk()->assertSee('DEMO 2026');
        $this->get('/tournament?tab=table')->assertOk()->assertSee('DEMO A');

        $snapshot = DB::table('matches')->get()->toJson();
        $this->refuse();
        $this->assertDatabaseCount('teams', 29);
        $this->assertSame($snapshot, DB::table('matches')->get()->toJson());
    }

    public function test_scorecard_serves_both_published_innings_and_excludes_drafts(): void
    {
        $this->populate();
        config(['tournament.enabled' => true]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();

        $match = DB::table('matches')->where('display_number', 1)->first();
        DB::table('player_match_stats')->where('match_id', $match->id)->where('record_state', 'draft')
            ->update(['player_name' => 'Private draft correction']);
        $this->get(route('tournament.scorecard', $match->id))->assertOk()
            ->assertSee('DEMO 2026 A1 Player 1')->assertSee('DEMO 2026 A2 Player 1')
            ->assertSee('480.00')->assertSee('2.2')->assertDontSee('Private draft correction');

        foreach (['live', 'upcoming'] as $status) {
            $id = DB::table('matches')->where('status', $status)->value('id');
            $this->get(route('tournament.scorecard', $id))->assertNotFound();
            $this->get('/tournament?status='.$status)->assertOk()->assertDontSee('class="tc-scorecard-open"', false);
        }
        config(['tournament.year' => 2027]);
        $this->get(route('tournament.scorecard', $match->id))->assertNotFound();
    }

    public function test_existing_group_blocks_demo_before_any_registration_changes(): void
    {
        DB::table('tournament_groups')->insert(['tournament_year' => 2026, 'name' => 'Existing group']);
        $this->refuse();
        $this->assertDatabaseCount('tournament_groups', 1);
        $this->assertDatabaseHas('tournament_groups', ['name' => 'Existing group']);
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_demo_requires_valid_configured_year(): void
    {
        config(['tournament.year' => 0]);
        try {
            $this->seed(TournamentDemoSeeder::class);
            $this->fail('An invalid year must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('TOURNAMENT_YEAR', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_demo_is_blocked_outside_local_and_testing(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->app->call([new TournamentDemoSeeder, 'run']);
            $this->fail('Production seeding must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('only in local or testing', $exception->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_calculation_failure_rolls_back_all_demo_records(): void
    {
        $this->mock(TournamentSummaryRebuildService::class, function ($mock) {
            $mock->shouldReceive('rebuild')->once()->andThrow(new \RuntimeException('Synthetic failure'));
        });
        try {
            $this->populate();
            $this->fail('The injected failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic failure', $exception->getMessage());
        }
        foreach (['companies', 'users', 'teams', 'team_members', 'tournament_groups', 'group_teams', 'matches', 'player_match_stats', 'player_tournament_stats'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
