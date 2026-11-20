<?php

namespace Tests\Feature;

use App\Models\PlayerMatchStat;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Services\Tournament\MatchStructureValidator;
use App\Services\Tournament\PlayerMatchConsistency;
use App\Services\Tournament\TournamentSummaryRebuildService;
use Database\Seeders\TournamentCompletedDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TournamentCompletedDemoSeederTest extends TestCase
{
    use DatabaseMigrations;

    private function populate(): void
    {
        config(['tournament.year' => 2026]);
        $this->seed(TournamentCompletedDemoSeeder::class);
    }

    public function test_completed_tournament_has_consistent_results_progression_and_public_champion(): void
    {
        $existing = Team::factory()->create();
        $before = $existing->fresh()->getAttributes();
        $this->populate();

        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('teams', 29);
        $this->assertDatabaseCount('team_members', 336);
        $this->assertDatabaseCount('tournament_groups', 7);
        $this->assertDatabaseCount('matches', 49);
        $this->assertDatabaseCount('player_match_stats', 392);
        $this->assertDatabaseCount('player_tournament_stats', 112);
        $this->assertSame(0, DB::table('player_match_stats')->where('record_state', 'draft')->count());
        $this->assertSame(8, DB::table('group_teams')->where('qualified', true)->count());
        $this->assertSame(28, DB::table('group_teams')->where('played', 3)->count());
        $this->assertSame(7, DB::table('group_teams')->where('wins', 3)->count());
        $this->assertSame(0, DB::table('users')->where('is_admin', true)->orWhere('is_scorer', true)->count());

        $matches = TournamentMatch::orderBy('display_number')->get();
        $this->assertSame(['G' => 42, 'QF' => 4, 'SF' => 2, 'F' => 1], $matches->countBy('stage')->all());
        $this->assertSame(range(1, 49), $matches->pluck('display_number')->all());
        foreach ($matches as $match) {
            $this->assertSame('finished', $match->status);
            $this->assertNotNull($match->result_published_at);
            $this->assertNotNull($match->player_stats_published_at);
            $this->app->make(PlayerMatchConsistency::class)->assertConsistent($match,
                PlayerMatchStat::where('match_id', $match->id)->get());
            // Validate every stored fixture against real qualification and preceding-round winners.
            $this->app->make(MatchStructureValidator::class)->validate(2026, $match->only([
                'stage', 'group_id', 'bracket_position', 'team1_id', 'team2_id',
                'balls_per_over', 'overs_per_innings', 'max_wickets',
            ]), $match->id);
        }

        $champion = Team::where('team_name', 'DEMO 2026 A1')->firstOrFail();
        $this->assertSame($champion->id, $matches->firstWhere('stage', 'F')->winner_id);
        $this->assertDatabaseHas('player_tournament_stats', [
            'team_member_id' => DB::table('team_members')->where('team_id', $champion->id)->orderBy('id')->value('id'),
            'tournament_year' => 2026, 'runs_scored' => 144,
        ]);

        config(['tournament.enabled' => true]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $this->get('/tournament?tab=bracket')->assertOk()->assertSee('Champion: DEMO 2026 A1')
            ->assertDontSee('To be confirmed');
        $this->get('/tournament?tab=table')->assertOk()->assertSee('DEMO A');
        $this->get('/tournament?tab=stats')->assertOk()->assertSee('DEMO 2026 A1 Player 1');

        $snapshot = $matches->toJson();
        try {
            $this->populate();
            $this->fail('Repeated seeding must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No demo data added', $exception->getMessage());
        }
        $this->assertSame($snapshot, TournamentMatch::orderBy('display_number')->get()->toJson());
        $this->assertDatabaseCount('teams', 29);
    }

    public function test_existing_tournament_blocks_seeding_before_registration_changes(): void
    {
        DB::table('tournament_groups')->insert(['tournament_year' => 2026, 'name' => 'Existing group']);
        try {
            $this->populate();
            $this->fail('An occupied year must be refused.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No demo data added', $exception->getMessage());
        }
        $this->assertDatabaseCount('teams', 0);
        $this->assertDatabaseCount('companies', 0);
    }

    public function test_invalid_year_is_refused(): void
    {
        config(['tournament.year' => 0]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TOURNAMENT_YEAR');
        $this->seed(TournamentCompletedDemoSeeder::class);
    }

    public function test_production_is_refused(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('only in local or testing');
            $this->app->call([new TournamentCompletedDemoSeeder, 'run']);
        } finally {
            $this->app->instance('env', 'testing');
            $this->assertDatabaseCount('companies', 0);
        }
    }

    public function test_rebuild_failure_rolls_back_the_entire_tournament(): void
    {
        $this->mock(TournamentSummaryRebuildService::class, function ($mock) {
            $mock->shouldReceive('rebuild')->once()->with(2026)->andThrow(new \RuntimeException('Synthetic failure'));
        });
        try {
            $this->populate();
            $this->fail('Rebuild failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic failure', $exception->getMessage());
        }
        foreach (['companies', 'users', 'teams', 'team_members', 'tournament_groups', 'group_teams', 'matches', 'player_match_stats', 'player_tournament_stats'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
