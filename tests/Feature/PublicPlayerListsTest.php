<?php

namespace Tests\Feature;

use App\Models\TeamMember;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPlayerListsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    private function summary(string $name, ?int $runs, string $gender = 'Male', array $changes = []): void
    {
        $member = TeamMember::factory()->create(['name' => 'PRIVATE-ROSTER-NAME']);
        DB::table('player_tournament_stats')->insert(array_replace([
            'tournament_year' => 2026, 'team_member_id' => $member->id, 'team_id' => $member->team_id,
            'player_name' => $name, 'player_gender' => $gender, 'runs_scored' => $runs,
            'runs_scored_recorded_count' => $runs === null ? 0 : 1, 'highest_score' => $runs,
        ], $changes));
    }

    public function test_default_list_shows_joint_leaders_cutoff_ties_and_honest_coverage(): void
    {
        foreach ([100, 100, 90, 80, 70, 70, 60] as $index => $runs) {
            $this->summary('Published player '.$index, $runs);
        }
        $this->summary('Unknown player', null);
        $response = $this->get('/tournament?tab=stats')->assertOk()->assertDontSee('aria-label="Player group"', false)
            ->assertSee('aria-label="Stats category"', false)
            ->assertDontSee('Filter player stats')
            ->assertSee('Most runs')->assertDontSee('Joint leader (provisional)')->assertSee('award/orange_cap.avif')->assertDontSee('Orange Cap (provisional)')
            ->assertSee('Published player 5')->assertDontSee('Published player 6')->assertDontSee('Unknown player')
            ->assertDontSee('How these rankings work')->assertSee('Refresh')->assertDontSee('PRIVATE-')->assertDontSee('Purple Cap');
        $this->assertCount(6, $response->viewData('rankingData')['entries']);
        $this->assertSame(7, $response->viewData('rankingData')['coverage']['players_with_recorded_metric']);
        $this->assertSame(8, $response->viewData('rankingData')['coverage']['players_with_published_entries']);
        $this->assertSame(2, substr_count($response->getContent(), 'award/orange_cap.avif'));
        $this->assertSame([1, 1, 3, 4, 5, 5], array_column($response->viewData('rankingData')['entries'], 'rank'));
    }

    public static function categories(): array
    {
        return [['runs', 'Most runs'], ['wickets', 'Most wickets'], ['sixes', 'Most sixes'],
            ['fours', 'Most fours'], ['dot_balls', 'Dot ball dominators'], ['highest_innings', 'Highest individual innings']];
    }

    #[DataProvider('categories')]
    public function test_each_category_renders_selected_figure_and_team(string $category, string $heading): void
    {
        $this->summary('Known player', 50, 'Female', ['wickets_taken' => 2, 'wickets_taken_recorded_count' => 1,
            'fours' => 6, 'fours_recorded_count' => 2, 'sixes' => 3, 'sixes_recorded_count' => 1,
            'dot_balls' => 8, 'dot_balls_recorded_count' => 2]);
        $teamName = DB::table('teams')->value('team_name');
        $response = $this->get('/tournament?tab=stats&category='.$category)->assertOk()->assertSee($heading)
            ->assertSee('Known player')->assertSee($teamName)->assertDontSee(' recorded match')->assertDontSee('tc-player-leader')
            ->assertDontSee('Super Over performances are excluded.')->assertDontSee('PRIVATE-');
        $this->assertSame($category, $response->viewData('rankingData')['category']);
        if ($category === 'wickets') {
            $response->assertSee('award/purple_cap.avif')->assertDontSee('Purple Cap (provisional)')->assertDontSee('Orange Cap');
        }
    }

    public function test_public_rankings_include_all_players_even_with_legacy_gender_parameters(): void
    {
        $this->summary('Male totals leader', 200, 'Male', ['highest_score' => 60]);
        $this->summary('Male innings leader', 100, 'Male', ['highest_score' => 90]);
        $this->summary('Female totals leader', 180, 'Female', ['highest_score' => 70]);
        $this->summary('Female innings leader', 110, 'Female', ['highest_score' => 100]);
        foreach (['Male', 'Female', 'Other'] as $gender) {
            $runs = $this->get('/tournament?tab=stats&category=runs&gender='.$gender)->assertOk()
                ->assertSee('All players')->assertSee('Male totals leader')->assertSee('Female totals leader')
                ->assertSee('Orange Cap')->assertDontSee('gender=', false);
            $innings = $this->get('/tournament?tab=stats&category=highest_innings&gender='.$gender)->assertOk();
            $this->assertSame('Male totals leader', $runs->viewData('rankingData')['entries'][0]['player_name']);
            $this->assertSame('Female innings leader', $innings->viewData('rankingData')['entries'][0]['player_name']);
        }
    }

    public function test_empty_and_zero_categories_do_not_invent_cap_holders(): void
    {
        $this->get('/tournament?tab=stats')->assertOk()->assertSee('No published figures are available');
        $this->summary('Zero player', 0);
        $this->get('/tournament?tab=stats')->assertOk()->assertSee('Zero player')->assertSee('No title holder yet.')
            ->assertDontSee('Orange Cap')->assertDontSee('Leader (provisional)');
    }

    public function test_names_and_teams_are_escaped_and_no_full_player_data_is_rendered(): void
    {
        $player = '<script>alert("player")</script>';
        $team = '<img src=x onerror=alert(1)>';
        $this->summary($player, 10);
        DB::table('teams')->update(['team_name' => $team, 'captain_email' => 'PRIVATE-CONTACT@example.test']);
        $this->get('/tournament?tab=stats')->assertOk()->assertSee($player)->assertDontSee($player, false)
            ->assertSee($team)->assertDontSee($team, false)->assertDontSee('PRIVATE-')
            ->assertDontSee('balls_faced')->assertDontSee('runs_conceded')->assertDontSee('player_gender');
    }

    public static function invalidFilters(): array
    {
        return [[['category' => ['runs']]], [['category' => '<script>PRIVATE-INJECTION</script>']]];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_stats_filters_have_a_safe_reset_response(array $filters): void
    {
        $this->get('/tournament?'.http_build_query(['tab' => 'stats', ...$filters]))->assertStatus(422)
            ->assertSee('This stats selection is unavailable.')->assertSee('Back to Stats')->assertDontSee('PRIVATE-INJECTION');
    }
}
