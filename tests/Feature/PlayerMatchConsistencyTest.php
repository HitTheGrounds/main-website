<?php

namespace Tests\Feature;

use App\Models\PlayerMatchStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Tournament\MatchResultService;
use App\Services\Tournament\PlayerPublicationService;
use App\Services\Tournament\PlayerStatsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlayerMatchConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['tournament.enabled' => true, 'tournament.writes_per_minute' => 1000]);
        $actor = User::factory()->create(['is_admin' => false, 'company_id' => null]);
        $actor->is_scorer = true;
        $actor->save();
        request()->attributes->set('user', $actor);
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'A', 'tournament_year' => 2026]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'team_id' => $team->id, 'tournament_year' => 2026]);
        }
        $id = DB::table('matches')->insertGetId(['tournament_year' => 2026, 'stage' => 'G', 'group_id' => $group,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4,
            'status' => 'finished', 'result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $teams[0]->id,
            'batting_first_id' => $teams[0]->id, 'team1_runs' => 50, 'team1_wickets' => 2, 'team1_deliveries' => 20,
            'team2_runs' => 40, 'team2_wickets' => 7, 'team2_deliveries' => 16,
            'result_published_at' => now(), 'structure_locked_at' => now()]);
        $member = TeamMember::factory()->create(['team_id' => $teams[0]->id]);

        return [TournamentMatch::findOrFail($id), $member];
    }

    private function expected(TournamentMatch $match, TeamMember $member): array
    {
        $rows = PlayerMatchStat::where('match_id', $match->id)->where('team_member_id', $member->id)->get();
        $expected = [];
        foreach (['draft', 'published'] as $state) {
            $row = $rows->firstWhere('record_state', $state);
            $expected[$state] = $row ? $row->only(['id', 'version']) : null;
        }

        return $expected;
    }

    private function save(TournamentMatch $match, TeamMember $member, array $input): void
    {
        app(PlayerStatsService::class)->saveDraft(2026, $match->id, $member->team_id, $member->id, $this->expected($match, $member), $input);
    }

    private function publish(TournamentMatch $match): void
    {
        $service = app(PlayerPublicationService::class);
        $review = $service->review(2026, $match->id);
        $service->publish(2026, $match->id, $review['version'], $review['player_stats_revision'], $review['receipt']);
    }

    public static function excessiveFigures(): array
    {
        return [[['runs_scored' => 51]], [['bowling_balls' => 17]], [['dot_balls' => 17]],
            [['bowling_balls' => 999]], [['fours' => 13]], [['sixes' => 9]]];
    }

    #[DataProvider('excessiveFigures')]
    public function test_incomplete_drafts_are_retained_but_known_match_contradictions_cannot_publish(array $figures): void
    {
        [$match, $member] = $this->fixture();
        $this->save($match, $member, $figures);
        try {
            $this->publish($match);
            $this->fail('Contradictory figures were published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('player', $exception->errors());
        }
        $this->assertDatabaseHas('player_match_stats', ['match_id' => $match->id, 'record_state' => 'draft']);
        $this->assertDatabaseCount('player_tournament_stats', 0);
        $this->assertNull($match->fresh()->player_stats_published_at);
    }

    public static function aggregateFigures(): array
    {
        return [[['runs_scored' => 30]], [['bowling_balls' => 9]], [['wickets_taken' => 4]]];
    }

    #[DataProvider('aggregateFigures')]
    public function test_retained_published_rows_are_included_and_withdrawals_can_repair_the_effective_batch(array $figures): void
    {
        [$match, $first] = $this->fixture();
        $this->save($match, $first, $figures);
        $this->publish($match);
        $second = TeamMember::factory()->create(['team_id' => $first->team_id]);
        $this->save($match, $second, $figures);
        try {
            $this->publish($match);
            $this->fail('The retained published contribution was ignored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('player', $exception->errors());
        }
        app(PlayerStatsService::class)->stageWithdrawal(2026, $match->id, $first->team_id, $first->id, $this->expected($match, $first));
        $this->publish($match);
        $this->assertDatabaseMissing('player_match_stats', ['team_member_id' => $first->id]);
        $this->assertDatabaseHas('player_match_stats', ['team_member_id' => $second->id, 'record_state' => 'published']);
    }

    public function test_partial_figures_zero_dnb_and_extras_do_not_require_equality_with_team_totals(): void
    {
        [$match, $member] = $this->fixture();
        $this->save($match, $member, ['runs_scored' => null, 'fours' => 2, 'sixes' => null, 'bowling_balls' => 10]);
        $dnb = TeamMember::factory()->create(['team_id' => $member->team_id]);
        $this->save($match, $dnb, ['batting_status' => 'did_not_bat', 'runs_scored' => 0, 'bowling_balls' => 6]);
        $this->publish($match);
        $this->assertDatabaseHas('player_match_stats', ['team_member_id' => $member->id, 'record_state' => 'published', 'runs_scored' => null, 'sixes' => null]);
        $this->assertDatabaseCount('player_tournament_stats', 2);
    }

    public function test_team_result_correction_cannot_make_retained_player_figures_impossible(): void
    {
        [$match, $member] = $this->fixture();
        $this->save($match, $member, ['runs_scored' => 40]);
        $this->publish($match);
        $results = app(MatchResultService::class);
        $results->saveDraft(2026, $match->id, $match->fresh()->version, ['result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $match->team1_id, 'batting_first_id' => $match->team1_id,
            'team1_runs' => 30, 'team1_wickets' => 2, 'team1_overs' => 5, 'team1_balls' => 0,
            'team2_runs' => 20, 'team2_wickets' => 7, 'team2_overs' => 4, 'team2_balls' => 0]);
        $review = $results->review(2026, $match->id);
        try {
            $results->publish(2026, $match->id, $review['version'], $review['draft_hash']);
            $this->fail('A result correction invalidated published player figures.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('player', $exception->errors());
        }
        $this->assertSame(50, $match->fresh()->team1_runs);
        $this->assertSame(30, $match->fresh()->draft_result['team1_runs']);
        app(PlayerStatsService::class)->stageWithdrawal(2026, $match->id, $member->team_id, $member->id, $this->expected($match, $member));
        $this->publish($match);
        $review = $results->review(2026, $match->id);
        $results->publish(2026, $match->id, $review['version'], $review['draft_hash']);
        $this->assertSame(30, $match->fresh()->team1_runs);
    }

    public static function invalidStoredFigures(): array
    {
        return [[['runs_scored' => 1, 'fours' => 2]], [['runs_scored' => 5, 'sixes' => 1]],
            [['batting_status' => 'did_not_bat', 'runs_scored' => 1]], [['batting_status' => 'did_not_bat', 'balls_faced' => 1]]];
    }

    public function test_restoring_reopened_history_rechecks_published_player_figures(): void
    {
        [$match, $member] = $this->fixture();
        DB::table('matches')->where('id', $match->id)->update(['status' => 'live']);
        DB::table('player_match_stats')->insert(['match_id' => $match->id, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'player_name' => $member->name, 'player_gender' => $member->gender,
            'record_state' => 'published', 'pending_action' => null, 'published_at' => now(), 'runs_scored' => 51]);
        try {
            app(\App\Services\Tournament\MatchSchedulingService::class)->updateSchedule(2026, $match->id, 1, ['status' => 'finished']);
            $this->fail('Inconsistent history was restored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('player', $exception->errors());
        }
        $this->assertSame('live', $match->fresh()->status);
        $this->assertDatabaseCount('player_tournament_stats', 0);
    }

    #[DataProvider('invalidStoredFigures')]
    public function test_database_guards_reject_direct_writes_of_known_contradictions(array $figures): void
    {
        [$match, $member] = $this->fixture();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('player_match_stats')->insert($figures + ['match_id' => $match->id,
            'team_id' => $member->team_id, 'team_member_id' => $member->id, 'player_name' => $member->name, 'player_gender' => $member->gender]));
    }

    public function test_additive_guards_preserve_legacy_rows_and_allow_an_explicit_withdrawal(): void
    {
        [$match, $member] = $this->fixture();
        $migration = require database_path('migrations/2026_11_19_000001_add_known_player_figure_guards.php');
        $migration->down();
        DB::table('player_match_stats')->insert(['match_id' => $match->id, 'team_id' => $member->team_id,
            'team_member_id' => $member->id, 'player_name' => $member->name, 'player_gender' => $member->gender,
            'record_state' => 'published', 'pending_action' => null, 'published_at' => now(), 'runs_scored' => 1, 'fours' => 2]);
        $migration->up();
        $this->assertDatabaseHas('player_match_stats', ['runs_scored' => 1, 'fours' => 2]);
        app(PlayerStatsService::class)->stageWithdrawal(2026, $match->id, $member->team_id, $member->id, $this->expected($match, $member));
        $this->publish($match);
        $this->assertDatabaseCount('player_match_stats', 0);
    }
}
