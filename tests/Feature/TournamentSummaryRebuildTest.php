<?php

namespace Tests\Feature;

use App\Models\GroupTeam;
use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use App\Services\Tournament\TournamentSummaryRebuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TournamentSummaryRebuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_requires_explicit_apply_without_touching_the_database(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->artisan('tournament:rebuild-summaries', ['year' => '2026'])
            ->expectsOutput('No changes made. Use --apply only after checking the target database and year.')
            ->assertExitCode(2);
        $this->assertSame([], $queries);
    }

    private function fixture(): array
    {
        // Maintenance is allowed before enabling the web feature. Fixtures live
        // only in the guarded in-memory database; no application database is used.
        config(['tournament.enabled' => false]);
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id, 'gender' => 'Female']);
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'A', 'tournament_year' => 2026]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['tournament_year' => 2026, 'group_id' => $group, 'team_id' => $team->id]);
        }
        $assignment = GroupTeam::where('team_id', $teams[0]->id)->firstOrFail();
        $assignment->point_adjustments = [['points' => -1, 'reason' => 'Committee penalty']];
        $assignment->qualified = true;
        $assignment->played = 99;
        $assignment->points = 99;
        $assignment->save();
        $history = $assignment->fresh()->point_adjustments;
        $match = DB::table('matches')->insertGetId(['tournament_year' => 2026, 'stage' => 'G', 'group_id' => $group,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'batting_first_id' => $teams[0]->id, 'balls_per_over' => 4,
            'status' => 'finished', 'result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $teams[0]->id,
            'result_published_at' => now(), 'team1_runs' => 100, 'team1_wickets' => 3, 'team1_deliveries' => 20,
            'team2_runs' => 90, 'team2_wickets' => 11, 'team2_deliveries' => 12,
            'draft_result' => json_encode(['team1_runs' => 999])]);
        DB::table('player_match_stats')->insert(['match_id' => $match, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'record_state' => 'published', 'pending_action' => null,
            'published_at' => now(), 'player_name' => 'Historical player', 'player_gender' => 'Female', 'runs_scored' => 40]);
        DB::table('player_match_stats')->insert(['match_id' => $match, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'record_state' => 'draft', 'pending_action' => 'replace',
            'player_name' => 'Historical player', 'player_gender' => 'Female', 'runs_scored' => 999]);
        $sources = [];
        foreach (['matches', 'player_match_stats', 'teams', 'team_members', 'users'] as $table) {
            $sources[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return compact('assignment', 'member', 'group', 'match', 'sources', 'history');
    }

    public function test_command_rebuilds_official_summaries_preserving_sources_and_committee_decisions(): void
    {
        ['assignment' => $assignment, 'member' => $member, 'sources' => $sources, 'history' => $history] = $this->fixture();
        $this->artisan('tournament:rebuild-summaries', ['year' => '2026', '--apply' => true])->assertExitCode(0);
        $this->assertSame(1, $assignment->fresh()->played);
        $this->assertSame(1, $assignment->fresh()->points);
        $this->assertSame('2.000000', $assignment->fresh()->nrr);
        $this->assertTrue($assignment->fresh()->qualified);
        $this->assertSame($history, $assignment->fresh()->point_adjustments);
        $total = PlayerTournamentStat::where('team_member_id', $member->id)->firstOrFail();
        $this->assertSame(40, $total->runs_scored);
        $this->assertSame(40, $total->highest_score);
        $this->assertNull($total->dot_balls);
        foreach ($sources as $table => $before) {
            $this->assertSame($before, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }

    public static function invalidYears(): array
    {
        return [['0'], ['-1'], ['10000'], ['02026'], ['2026.0'], ['2e3'], [' 2026'], ['2026 '], ['+2026'], ['all'], ['']];
    }

    #[DataProvider('invalidYears')]
    public function test_malformed_year_is_rejected_before_database_work(string $year): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $this->artisan('tournament:rebuild-summaries', ['year' => $year, '--apply' => true])
            ->expectsOutput('Enter one whole tournament year from 1 to 9999.')->assertExitCode(2);
        $this->assertSame([], $queries);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['tournament_groups', 'group_teams', 'matches', 'player_match_stats', 'player_tournament_stats', 'teams', 'team_members', 'users'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }

    public function test_repeated_rebuilds_agree_exactly_with_real_publication_without_version_or_timestamp_churn(): void
    {
        ['match' => $matchId, 'member' => $member] = $this->fixture();
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $actor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $actor->is_scorer = true;
        $actor->save();
        request()->attributes->set('user', $actor);
        $match = \App\Models\TournamentMatch::findOrFail($matchId);
        $results = app(MatchResultService::class);
        $results->saveDraft(2026, $matchId, $match->version, ['result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $match->team1_id, 'batting_first_id' => $match->team1_id,
            'team1_runs' => 100, 'team1_wickets' => 3, 'team1_overs' => 5, 'team1_balls' => 0,
            'team2_runs' => 90, 'team2_wickets' => 11, 'team2_overs' => 3, 'team2_balls' => 0]);
        $review = $results->review(2026, $matchId);
        $results->publish(2026, $matchId, $review['version'], $review['draft_hash']);
        $expected = [];
        foreach (['draft', 'published'] as $state) {
            $row = PlayerMatchStat::where('match_id', $matchId)->where('record_state', $state)->firstOrFail();
            $expected[$state] = ['id' => $row->id, 'version' => $row->version];
        }
        app(PlayerStatsService::class)->saveDraft(2026, $matchId, $member->team_id, $member->id, $expected, ['runs_scored' => 45]);
        $publisher = app(PlayerPublicationService::class);
        $review = $publisher->review(2026, $matchId);
        $publisher->publish(2026, $matchId, $review['version'], $review['player_stats_revision'], $review['receipt']);
        $before = $this->snapshot();
        $this->travel(2)->minutes();
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->artisan('tournament:rebuild-summaries', ['year' => '2026', '--apply' => true])->assertExitCode(0);
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function test_stale_summaries_are_removed_only_for_requested_year_without_eligible_sources(): void
    {
        ['member' => $member, 'match' => $matchId] = $this->fixture();
        $this->artisan('tournament:rebuild-summaries', ['year' => '2026', '--apply' => true])->assertExitCode(0);
        // Preserve another edition's derived rows, even when they need a rebuild.
        $other = PlayerTournamentStat::firstOrFail()->replicate();
        $other->tournament_year = 2027;
        $other->save();
        $otherBefore = $other->fresh()->getAttributes();
        DB::table('matches')->where('id', $matchId)->update(['result_type' => 'no_result', 'winner_id' => null, 'result_method' => null]);
        $sources = $this->snapshot();
        $this->artisan('tournament:rebuild-summaries', ['year' => '2026', '--apply' => true])->assertExitCode(0);
        $this->assertFalse(PlayerTournamentStat::where('tournament_year', 2026)->where('team_member_id', $member->id)->exists());
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
        foreach (['matches', 'player_match_stats', 'teams', 'team_members', 'users'] as $table) {
            $this->assertSame($sources[$table], $this->snapshot()[$table]);
        }
    }

    public function test_empty_year_does_not_change_other_editions(): void
    {
        $this->fixture();
        $before = $this->snapshot();
        $this->artisan('tournament:rebuild-summaries', ['year' => '2027', '--apply' => true])
            ->expectsOutput('Tournament 2027 summaries rebuilt: 0 groups checked, 0 standings updated, 0 players checked.')->assertExitCode(0);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_late_summary_failure_rolls_back_standings_versions_and_all_source_data_without_leaking_error_text(): void
    {
        $this->fixture();
        $before = $this->snapshot();
        $event = 'eloquent.created: '.PlayerTournamentStat::class;
        Event::listen($event, fn () => throw new RuntimeException('PRIVATE raw SQL and player details must not be printed'));
        try {
            $this->artisan('tournament:rebuild-summaries', ['year' => '2026', '--apply' => true])
                ->expectsOutput('Rebuild failed. No summary changes were committed. Check source validity or lock contention and retry.')
                ->doesntExpectOutputToContain('PRIVATE')->assertExitCode(1);
        } finally {
            Event::forget($event);
        }
        $this->assertSame($before, $this->snapshot());
        $this->artisan('tournament:rebuild-summaries', ['year' => '2026', '--apply' => true])->assertExitCode(0);
        $this->assertSame(40, PlayerTournamentStat::firstOrFail()->runs_scored);
    }

    public function test_invalid_official_player_figures_roll_back_earlier_standings_repairs(): void
    {
        ['match' => $matchId] = $this->fixture();
        // Foreign keys remain valid, but this source team is outside its match.
        $outside = Team::factory()->create(['approved' => true]);
        DB::table('player_match_stats')->where('match_id', $matchId)->where('record_state', 'published')
            ->update(['team_id' => $outside->id]);
        $before = $this->snapshot();
        $this->artisan('tournament:rebuild-summaries', ['year' => '2026', '--apply' => true])->assertExitCode(1);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_rebuild_service_rejects_non_console_invocation_before_queries(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $console = new \ReflectionProperty(\Illuminate\Foundation\Application::class, 'isRunningInConsole');
        $previous = $console->getValue($this->app);
        $console->setValue($this->app, false);
        try {
            app(TournamentSummaryRebuildService::class)->rebuild(2026);
            $this->fail('Expected console-only denial.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        } finally {
            $console->setValue($this->app, $previous);
        }
        $this->assertSame([], $queries);
    }
}
