<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicTournamentMatchesTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    private function fixture(int $count = 2): array
    {
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'Group G', 'tournament_year' => 2026]);
        $teams = Team::factory()->count($count)->create(['approved' => true, 'captain_email' => 'PRIVATE-CONTACT@example.test']);

        return [$group, $teams];
    }

    private function match(int $group, int $a, int $b, array $changes = []): int
    {
        return DB::table('matches')->insertGetId(array_replace([
            'tournament_year' => 2026, 'stage' => 'G', 'group_id' => $group,
            'team1_id' => $a, 'team2_id' => $b, 'balls_per_over' => 4,
            'draft_result' => json_encode(['team1_runs' => 987654, 'result_note' => 'PRIVATE-DRAFT']),
            'result_note' => 'PRIVATE-NOTE',
        ], $changes));
    }

    public function test_unfinished_cards_hide_retained_scores_and_private_fields(): void
    {
        [$group, $teams] = $this->fixture(4);
        foreach (['upcoming', 'live'] as $index => $status) {
            $this->match($group, $teams[0]->id, $teams[$index + 1]->id, [
                'status' => $status, 'team1_runs' => 543210, 'result_published_at' => now(),
            ]);
        }
        $hidden = $this->match($group, $teams[0]->id, $teams[3]->id, ['status' => 'finished']);
        $response = $this->get('/tournament')->assertOk()->assertSee('Upcoming')->assertSee('In progress')
            ->assertSee('Scores appear after the result is published.')->assertSee('Schedule to be confirmed')
            ->assertDontSee('543210')->assertDontSee('987654')->assertDontSee('PRIVATE-')
            ->assertDontSee('match-'.$hidden.'-heading', false);
        $this->assertCount(2, $response->viewData('matchData')['matches']);
    }

    public function test_official_cards_show_real_zero_unknowns_formats_and_local_schedule(): void
    {
        [$group, $teams] = $this->fixture(3);
        foreach ([4, 6] as $index => $balls) {
            $this->match($group, $teams[0]->id, $teams[$index + 1]->id, [
                'status' => 'finished', 'result_published_at' => now(), 'result_type' => 'no_result',
                'balls_per_over' => $balls, 'team1_runs' => 0, 'team1_wickets' => 0, 'team1_deliveries' => 13,
                'scheduled_at' => '2026-11-20 03:00:00',
            ]);
        }
        $this->get('/tournament')->assertOk()->assertSee('4 balls per over')->assertSee('6 balls per over')
            ->assertSee('0/0')->assertSee('3.1 overs')->assertSee('2.1 overs')->assertSee('Runs not recorded')
            ->assertSee('Wickets not recorded')->assertSee('Overs not recorded')->assertSee('No result.')
            ->assertSee('20 Nov 2026, 8:30 AM')->assertSee('Sri Lanka time');
    }

    public static function outcomes(): array
    {
        return [
            ['tie', null, 'Match tied.'], ['abandoned', null, 'Match abandoned.'],
            ['win', 'normal', 'won by 10 runs.'], ['win', 'super_over', 'won after a Super Over.'],
            ['win', 'coin_toss', 'won by coin toss.'],
            ['win', 'revised_target', 'won by the official revised-target decision.'],
            ['win', 'official_decision', 'won by official decision.'],
        ];
    }

    #[DataProvider('outcomes')]
    public function test_published_outcome_descriptions(string $type, ?string $method, string $description): void
    {
        [$group, $teams] = $this->fixture();
        $this->match($group, $teams[0]->id, $teams[1]->id, [
            'status' => 'finished', 'result_published_at' => now(), 'result_type' => $type,
            'result_method' => $method, 'winner_id' => $type === 'win' ? $teams[0]->id : null,
            'batting_first_id' => $teams[0]->id, 'team1_runs' => 70, 'team2_runs' => 60,
        ]);
        $this->get('/tournament')->assertOk()->assertSee($description);
    }

    public function test_filters_and_pagination_keep_only_canonical_safe_parameters(): void
    {
        [$group, $teams] = $this->fixture(14);
        foreach ($teams->skip(1) as $team) {
            $this->match($group, $teams[0]->id, $team->id, ['status' => 'live']);
        }
        $query = ['tab' => 'matches', 'stage' => 'G', 'status' => 'live', 'group_id' => $group, 'sort' => 'newest'];
        $first = $this->get('/tournament?'.http_build_query([...$query, 'PRIVATE-QUERY' => 'PRIVATE-VALUE']))
            ->assertOk()->assertSee('method="GET"', false)->assertSee('Apply filters')->assertSee('Page 1 of 2')
            ->assertSee(route('tournament.index', [...$query, 'page' => 2]))->assertDontSee('PRIVATE-VALUE');
        $second = $this->get('/tournament?'.http_build_query([...$query, 'page' => 2]))->assertOk();
        $this->assertCount(12, $first->viewData('matchData')['matches']);
        $this->assertCount(1, $second->viewData('matchData')['matches']);
        $this->assertSame([], array_intersect(array_column($first->viewData('matchData')['matches'], 'id'),
            array_column($second->viewData('matchData')['matches'], 'id')));
        $this->get('/tournament?stage=F')->assertOk()->assertSee('No public matches match these filters.');
        $this->get('/tournament?page=99')->assertOk()->assertSee('No matches on this page.');
    }

    public function test_empty_edition_and_escaped_public_text(): void
    {
        $this->get('/tournament')->assertOk()->assertSee('No public matches are available yet.');
        [$group, $teams] = $this->fixture();
        $name = '<script>alert("team")</script>';
        DB::table('teams')->where('id', $teams[0]->id)->update(['team_name' => $name]);
        $venue = '<img src=x onerror=alert(1)>';
        $this->match($group, $teams[0]->id, $teams[1]->id, ['venue' => $venue]);
        $this->get('/tournament')->assertOk()->assertSee($name)->assertDontSee($name, false)
            ->assertSee($venue)->assertDontSee($venue, false)->assertDontSee('PRIVATE-');
    }

    public static function invalidFilters(): array
    {
        return [[['stage' => ['G']]], [['status' => ['live']]], [['sort' => ['schedule']]],
            [['group_id' => ['1']]], [['page' => 0]], [['sort' => 'id desc; DROP TABLE users']], [['status' => 'draft']]];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_show_a_reset_link_without_reflecting_input(array $filters): void
    {
        $this->get('/tournament?'.http_build_query($filters))->assertStatus(422)
            ->assertSee('Select valid match filters and try again.')->assertSee('Reset filters')
            ->assertDontSee('DROP TABLE')->assertDontSee('Match list');
    }
}
