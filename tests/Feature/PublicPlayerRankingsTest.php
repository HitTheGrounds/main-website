<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use App\Services\Tournament\PublicTournamentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PublicPlayerRankingsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tournament.enabled' => true]);
    }

    private function summary(?int $runs, string $gender = 'Male', array $changes = [], int $year = 2026): int
    {
        $member = TeamMember::factory()->create(['name' => 'PRIVATE-REGISTRATION-NAME', 'gender' => 'Other']);
        DB::table('player_tournament_stats')->insert(array_replace([
            'tournament_year' => $year, 'team_member_id' => $member->id, 'team_id' => $member->team_id,
            'player_name' => 'Published name '.$member->id, 'player_gender' => $gender,
            'runs_scored' => $runs, 'runs_scored_recorded_count' => $runs === null ? 0 : 1,
            'highest_score' => $runs === null ? null : min($runs, 100),
        ], $changes));

        return $member->id;
    }

    public function test_tournament_wickets_tab_only_ranks_male_players(): void
    {
        config(['tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();

        $male = $this->summary(10, 'Male', ['wickets_taken' => 2, 'wickets_taken_recorded_count' => 1]);
        $female = $this->summary(20, 'Female', ['wickets_taken' => 5, 'wickets_taken_recorded_count' => 1]);
        $other = $this->summary(30, 'Other', ['wickets_taken' => 4, 'wickets_taken_recorded_count' => 1]);

        $response = $this->get('/tournament?tab=stats&category=wickets&gender=Female')
            ->assertOk()->assertSee('Player (Male)')->assertSee('Most wickets, Male players')
            ->assertSee('Published name '.$male)
            ->assertDontSee('Published name '.$female)->assertDontSee('Published name '.$other);
        $ranking = $response->viewData('rankingData');
        $this->assertSame([$male], array_column($ranking['entries'], 'player_id'));
        $this->assertSame([$male], $ranking['leader_ids']);
        $this->assertSame(1, $ranking['entries'][0]['rank']);

        $runs = $this->get('/tournament?tab=stats&category=runs')->assertOk()
            ->assertSee('Most runs, All players')->assertDontSee('Player (Male)');
        $this->assertSame([$other, $female, $male], array_column($runs->viewData('rankingData')['entries'], 'player_id'));
    }

    public function test_top_five_includes_every_cutoff_tie_and_keeps_joint_leaders(): void
    {
        $ids = [];
        foreach ([100, 100, 90, 80, 70, 70, 70, 60] as $runs) {
            $ids[] = $this->summary($runs);
        }
        $result = app(PublicTournamentService::class)->rankings(2026);
        $this->assertSame([1, 1, 3, 4, 5, 5, 5], array_column($result['entries'], 'rank'));
        $this->assertSame(array_slice($ids, 0, 7), array_column($result['entries'], 'player_id'));
        $this->assertSame(array_slice($ids, 0, 2), $result['leader_ids']);
        $this->assertSame(8, $result['coverage']['players_with_recorded_metric']);
    }

    public static function categories(): array
    {
        return [['runs', 'runs_scored'], ['wickets', 'wickets_taken'], ['sixes', 'sixes'],
            ['fours', 'fours'], ['dot_balls', 'dot_balls'], ['highest_innings', 'highest_score']];
    }

    #[DataProvider('categories')]
    public function test_each_category_uses_its_own_figure_and_recorded_population(string $category, string $metric): void
    {
        $count = $metric === 'highest_score' ? 'runs_scored_recorded_count' : $metric.'_recorded_count';
        $winner = $this->summary(7, 'Female', [$metric => 7, $count => 2]);
        $this->summary(3, 'Male', [$metric => 3, $count => 1]);
        $this->summary(null);
        $result = app(PublicTournamentService::class)->rankings(2026, ['category' => $category]);
        $this->assertSame($winner, $result['entries'][0]['player_id']);
        $this->assertSame(7, $result['entries'][0]['value']);
        $this->assertSame(2, $result['entries'][0]['recorded_count']);
        $this->assertSame(['players_with_published_entries' => 3, 'players_with_recorded_metric' => 2], $result['coverage']);
    }

    public function test_gender_uses_published_snapshots_and_year_scopes_every_query(): void
    {
        $male = $this->summary(30, 'Male');
        $female = $this->summary(40, 'Female');
        $other = $this->summary(50, 'Other');
        $this->summary(200, 'Female', [], 2027);
        $service = app(PublicTournamentService::class);
        $this->assertSame([$other, $female, $male], array_column($service->rankings(2026)['entries'], 'player_id'));
        foreach (['Male' => $male, 'Female' => $female] as $gender => $id) {
            $this->assertSame([$id], array_column($service->rankings(2026, ['gender' => $gender])['entries'], 'player_id'));
            $this->assertSame([$id], array_column($service->rankings(2026, ['gender' => $gender, 'category' => 'highest_innings'])['entries'], 'player_id'));
        }
    }

    public function test_unknowns_are_excluded_known_zeros_are_retained_without_a_title_holder(): void
    {
        $this->summary(null);
        $zero = $this->summary(0);
        $result = app(PublicTournamentService::class)->rankings(2026);
        $this->assertSame([$zero], array_column($result['entries'], 'player_id'));
        $this->assertSame(0, $result['entries'][0]['value']);
        $this->assertSame([], $result['leader_ids']);
        $empty = app(PublicTournamentService::class)->rankings(2026, ['category' => 'dot_balls']);
        $this->assertSame([], $empty['entries']);
        $this->assertSame([], $empty['leader_ids']);
        $this->assertSame(0, $empty['coverage']['players_with_recorded_metric']);
    }

    public function test_total_runs_and_highest_innings_are_different_rankings(): void
    {
        $total = $this->summary(200, 'Male', ['highest_score' => 60]);
        $innings = $this->summary(150, 'Male', ['highest_score' => 90]);
        $service = app(PublicTournamentService::class);
        $this->assertSame([$total], $service->rankings(2026)['leader_ids']);
        $this->assertSame([$innings], $service->rankings(2026, ['category' => 'highest_innings'])['leader_ids']);
    }

    public function test_large_totals_keep_exact_order_without_float_rounding(): void
    {
        $low = $this->summary(9007199254740992);
        $high = $this->summary(9007199254740993);
        $result = app(PublicTournamentService::class)->rankings(2026);
        $this->assertSame([$high, $low], array_column($result['entries'], 'player_id'));
        $this->assertSame([1, 2], array_column($result['entries'], 'rank'));
        $this->assertSame(9007199254740993, $result['entries'][0]['value']);
    }

    public function test_equal_runs_use_higher_strike_rate_and_preserve_exact_rate_ties(): void
    {
        $slow = $this->summary(100, changes: ['balls_faced' => 80, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $fast = $this->summary(100, changes: ['balls_faced' => 50, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $joint = $this->summary(100, changes: ['balls_faced' => 50, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $lower = $this->summary(90, changes: ['balls_faced' => 10, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $result = app(PublicTournamentService::class)->rankings(2026);
        $this->assertSame([$fast, $joint, $slow, $lower], array_column($result['entries'], 'player_id'));
        $this->assertSame([1, 1, 3, 4], array_column($result['entries'], 'rank'));
        $this->assertSame(['200.00', '200.00', '125.00', '900.00'], array_column($result['entries'], 'rate'));
        $this->assertSame([$fast, $joint], $result['leader_ids']);
    }

    public function test_equal_wickets_use_lower_economy_including_a_genuine_zero(): void
    {
        $base = ['wickets_taken' => 5, 'wickets_taken_recorded_count' => 1,
            'runs_conceded_recorded_count' => 1, 'bowling_overs_units' => 48, 'economy_complete' => true];
        $expensive = $this->summary(0, changes: [...$base, 'runs_conceded' => 40]);
        $cheap = $this->summary(0, changes: [...$base, 'runs_conceded' => 24]);
        $zero = $this->summary(0, changes: [...$base, 'runs_conceded' => 0]);
        $result = app(PublicTournamentService::class)->rankings(2026, ['category' => 'wickets']);
        $this->assertSame([$zero, $cheap, $expensive], array_column($result['entries'], 'player_id'));
        $this->assertSame(['0.00', '6.00', '10.00'], array_column($result['entries'], 'rate'));
        $this->assertSame([$zero], $result['leader_ids']);
    }

    public function test_rates_are_compared_before_rounding_and_without_integer_overflow(): void
    {
        $slow = $this->summary(9007199254740993, changes: ['balls_faced' => 9007199254740993,
            'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $fast = $this->summary(9007199254740993, changes: ['balls_faced' => 9007199254740992,
            'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $result = app(PublicTournamentService::class)->rankings(2026);
        $this->assertSame([$fast, $slow], array_column($result['entries'], 'player_id'));
        $this->assertSame([1, 2], array_column($result['entries'], 'rank'));
        $this->assertSame(['100.00', '100.00'], array_column($result['entries'], 'rate'));
        $this->assertSame([$fast], $result['leader_ids']);
    }

    public function test_equal_economy_fractions_share_a_rank_and_nearby_rates_do_not(): void
    {
        $base = ['wickets_taken' => 4, 'wickets_taken_recorded_count' => 1,
            'runs_conceded_recorded_count' => 1, 'economy_complete' => true];
        $expensive = $this->summary(0, changes: [...$base, 'runs_conceded' => 20001, 'bowling_overs_units' => 40000]);
        $cheap = $this->summary(0, changes: [...$base, 'runs_conceded' => 1, 'bowling_overs_units' => 2]);
        $joint = $this->summary(0, changes: [...$base, 'runs_conceded' => 2, 'bowling_overs_units' => 4]);
        $result = app(PublicTournamentService::class)->rankings(2026, ['category' => 'wickets']);
        $this->assertSame([$cheap, $joint, $expensive], array_column($result['entries'], 'player_id'));
        $this->assertSame([1, 1, 3], array_column($result['entries'], 'rank'));
        $this->assertSame(['6.00', '6.00', '6.00'], array_column($result['entries'], 'rate'));
        $this->assertSame([$cheap, $joint], $result['leader_ids']);
    }

    public function test_missing_economy_inputs_and_zero_overs_do_not_become_zero_economy(): void
    {
        $base = ['wickets_taken' => 4, 'wickets_taken_recorded_count' => 1,
            'runs_conceded_recorded_count' => 1, 'economy_complete' => true];
        $this->summary(0, changes: [...$base, 'runs_conceded' => 0, 'bowling_overs_units' => 0]);
        $this->summary(0, changes: [...$base, 'runs_conceded' => 0, 'bowling_overs_units' => null]);
        $this->summary(0, changes: [...$base, 'runs_conceded' => null, 'runs_conceded_recorded_count' => 0, 'bowling_overs_units' => 12]);
        $this->summary(0, changes: [...$base, 'runs_conceded' => 10, 'bowling_overs_units' => 12, 'economy_complete' => false]);
        $result = app(PublicTournamentService::class)->rankings(2026, ['category' => 'wickets']);
        $this->assertSame([null, null, null, null], array_column($result['entries'], 'rate'));
        $this->assertSame([1, 1, 1, 1], array_column($result['entries'], 'rank'));
    }

    public function test_cutoff_is_applied_after_rate_sorting_and_keeps_true_fifth_place_ties(): void
    {
        foreach ([200, 180, 160, 140] as $runs) {
            $this->summary($runs);
        }
        $this->summary(100, changes: ['balls_faced' => 80, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $fast = $this->summary(100, changes: ['balls_faced' => 40, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $joint = $this->summary(100, changes: ['balls_faced' => 40, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $result = app(PublicTournamentService::class)->rankings(2026);
        $this->assertCount(6, $result['entries']);
        $this->assertSame([$fast, $joint], array_slice(array_column($result['entries'], 'player_id'), 4));
        $this->assertSame([1, 2, 3, 4, 5, 5], array_column($result['entries'], 'rank'));
    }

    public function test_missing_or_zero_denominator_rates_leave_the_whole_total_group_tied(): void
    {
        $known = $this->summary(100, changes: ['balls_faced' => 50, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $unknown = $this->summary(100);
        $zero = $this->summary(100, changes: ['balls_faced' => 0, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => true]);
        $incomplete = $this->summary(100, changes: ['balls_faced' => 20, 'balls_faced_recorded_count' => 1, 'strike_rate_complete' => false]);
        $result = app(PublicTournamentService::class)->rankings(2026);
        $this->assertSame([1, 1, 1, 1], array_column($result['entries'], 'rank'));
        $this->assertSame(['200.00', null, null, null], array_column($result['entries'], 'rate'));
        $this->assertSame([$known, $unknown, $zero, $incomplete], $result['leader_ids']);
    }

    public function test_read_projection_is_private_field_free_without_source_joins_or_writes(): void
    {
        $this->summary(10);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = app(PublicTournamentService::class)->rankings(2026);
        $sql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();
        foreach (['team_members', 'player_match_stats', 'captain_email', 'highest_score_match_id', 'updated_at',
            'insert ', 'update ', 'delete ', 'for update', 'advisory'] as $private) {
            $this->assertStringNotContainsString($private, $sql);
        }
        $this->assertStringNotContainsString('PRIVATE-', json_encode($result));
        $this->assertSame(['rank', 'player_id', 'player_name', 'team_id', 'team_name', 'value', 'recorded_count', 'rate'], array_keys($result['entries'][0]));
    }

    public static function invalidFilters(): array
    {
        return [[['category' => 'runs_scored']], [['category' => 'runs desc; drop table users']],
            [['category' => ['runs']]], [['gender' => ['Male']]], [['gender' => 'Other']],
            [['gender' => '']], [['category' => null]], [['limit' => 100]], [['year' => 2027]]];
    }

    #[DataProvider('invalidFilters')]
    public function test_filters_are_strictly_allowlisted(array $filters): void
    {
        $this->expectException(ValidationException::class);
        app(PublicTournamentService::class)->rankings(2026, $filters);
    }

    public function test_invalid_year_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(PublicTournamentService::class)->rankings(0);
    }

    public function test_disabled_feature_blocks_ranking_reads(): void
    {
        config(['tournament.enabled' => false]);
        try {
            app(PublicTournamentService::class)->rankings(2026);
            $this->fail('Disabled rankings must be blocked.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
