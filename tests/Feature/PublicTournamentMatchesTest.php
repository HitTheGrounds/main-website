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
        foreach (['upcoming' => 'Yet to start.', 'live' => 'Match in progress.'] as $status => $note) {
            $response = $this->get('/tournament?status='.$status)->assertOk()->assertSee('Upcoming')->assertSee('In progress')
                ->assertSee($note)->assertSee('Scores appear after the result is published.')
                ->assertDontSee('Schedule to be confirmed')->assertDontSee('543210')->assertDontSee('987654')
                ->assertDontSee('PRIVATE-')->assertDontSee('match-'.$hidden.'-heading', false);
            $this->assertCount(1, $response->viewData('matchData')['matches']);
        }
        // With no status chosen the Matches tab opens on Finished; unpublished results stay hidden.
        $response = $this->get('/tournament')->assertOk()->assertSee('aria-current="page"', false)
            ->assertDontSee('match-'.$hidden.'-heading', false);
        $this->assertSame('finished', $response->viewData('matchData')['filters']['status']);
        $this->assertCount(0, $response->viewData('matchData')['matches']);
    }

    public function test_official_cards_show_real_zero_unknowns_formats_and_local_schedule(): void
    {
        [$group, $teams] = $this->fixture(3);
        foreach ([4, 6] as $index => $balls) {
            $this->match($group, $teams[0]->id, $teams[$index + 1]->id, [
                'status' => 'finished', 'result_published_at' => now(), 'result_type' => 'no_result',
                'balls_per_over' => $balls, 'team1_runs' => 0, 'team1_wickets' => 0, 'team1_deliveries' => 13,
                'scheduled_at' => '2026-11-20 03:00:00', 'team2_runs' => $index === 1 ? 5 : null,
            ]);
        }
        // Cards no longer print the schedule, venue or match format. An innings with nothing
        // recorded gets one line; a partly recorded innings names each missing part.
        $this->get('/tournament')->assertOk()->assertSee('0/0')->assertSeeText('3.1 overs')->assertSeeText('2.1 overs')
            ->assertSee('Score not recorded')->assertDontSee('Runs not recorded')
            ->assertSee('Wickets not recorded')->assertSee('Overs not recorded')
            ->assertSee('No result.')->assertDontSee('balls per over')->assertDontSee('20 Nov 2026, 8:30 AM');
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
        $this->get('/tournament?status=live&page=99')->assertOk()->assertSee('No matches on this page.');
    }

    public static function matchStatuses(): array
    {
        return [['finished'], ['upcoming'], ['live']];
    }

    #[DataProvider('matchStatuses')]
    public function test_status_tabs_sort_by_latest_update_or_latest_publication(string $status): void
    {
        [$group, $teams] = $this->fixture(6);
        $ids = [];
        // Creation order, scheduled time, and updated_at on finished rows must
        // not override the requested publication/update order.
        foreach ([['10:00', '12:00'], ['11:00', '09:00'], ['12:00', null], ['08:00', null], ['10:00', null]] as $i => [$result, $players]) {
            $details = ['status' => $status, 'scheduled_at' => '2026-11-25 20:00:00',
                'updated_at' => $i === 3 ? null : '2026-11-20 '.(['12:00', '11:00', '12:00', '08:00', '10:00'][$i]).':00'];
            if ($status === 'finished') {
                $details += ['result_type' => 'no_result', 'result_published_at' => '2026-11-20 '.$result.':00',
                    'player_stats_published_at' => $players === null ? null : '2026-11-20 '.$players.':00'];
                $details['updated_at'] = '2026-11-21 20:00:00';
            }
            $ids[] = $this->match($group, $teams[0]->id, $teams[$i + 1]->id, $details);
        }
        $expected = [$ids[2], $ids[0], $ids[1], $ids[4], $ids[3]];
        $response = $this->get('/tournament?status='.$status)->assertOk()
            ->assertSee($status === 'finished' ? 'Latest published first' : 'Latest updated first');
        $this->assertSame($expected, array_column($response->viewData('matchData')['matches'], 'id'));
        if ($status === 'finished') {
            $this->assertSame($expected,
                array_column($this->get('/tournament')->assertOk()->viewData('matchData')['matches'], 'id'));
        }
        $this->assertSame($expected, array_column($this->get('/tournament?status='.$status.'&sort=schedule')
            ->assertOk()->viewData('matchData')['matches'], 'id'));
    }

    public function test_empty_edition_and_escaped_public_text(): void
    {
        $this->get('/tournament')->assertOk()->assertSee('No finished matches have published results yet.');
        $this->get('/tournament?status=upcoming')->assertOk()->assertSee('No upcoming matches are scheduled right now.');
        [$group, $teams] = $this->fixture();
        $name = '<script>alert("team")</script>';
        DB::table('teams')->where('id', $teams[0]->id)->update(['team_name' => $name]);
        $venue = '<img src=x onerror=alert(1)>';
        $this->match($group, $teams[0]->id, $teams[1]->id, ['venue' => $venue]);
        $this->get('/tournament?status=upcoming')->assertOk()->assertSee($name)->assertDontSee($name, false)
            ->assertDontSee($venue, false)->assertDontSee('PRIVATE-');
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
