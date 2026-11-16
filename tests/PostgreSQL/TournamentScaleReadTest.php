<?php

namespace Tests\PostgreSQL;

use App\Models\Team;
use App\Models\User;
use App\Services\Tournament\TournamentGroupService;
use App\Services\Tournament\TournamentSummaryRebuildService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/** Dataset/query rehearsal only. This is not a concurrent HTTP/network load test. */
class TournamentScaleReadTest extends PostgreSqlTestCase
{
    use DatabaseMigrations;

    public function test_planned_size_pages_and_query_growth_with_sequential_requests(): void
    {
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
        $empty = [];
        foreach (['matches', 'table', 'bracket', 'stats'] as $tab) {
            [, $metric] = $this->measure('/tournament?tab='.$tab);
            $empty[$tab] = $metric['queries'];
        }
        $scorers = $this->seedTournament();
        $this->assertDatabaseCount('teams', 28);
        $this->assertDatabaseCount('tournament_groups', 7);
        $this->assertDatabaseCount('matches', 49);
        $this->assertDatabaseCount('team_members', 336);
        $this->assertDatabaseCount('player_match_stats', 1176);
        $this->assertDatabaseCount('player_tournament_stats', 336);
        $metrics = [];
        foreach (range(0, 99) as $request) {
            $tab = ['matches', 'table', 'bracket', 'stats'][$request % 4];
            [$response, $metric] = $this->measure('/tournament?tab='.$tab);
            $metrics[$tab][] = $metric;
            // The fixed read queries may add a teams/join lookup once populated;
            // they must not grow one SQL query per group/team/player/fixture.
            $this->assertLessThanOrEqual($empty[$tab] + 2, $metric['queries'], $tab.' query growth');
            if ($tab === 'matches') {
                $data = $response->viewData('matchData');
                $this->assertSame(49, $data['pagination']['total']);
                $this->assertCount(12, $data['matches']);
                $this->assertCount(7, $data['groups']);
            } elseif ($tab === 'table') {
                $groups = $response->viewData('tableData')['groups'];
                $this->assertCount(7, $groups);
                foreach ($groups as $group) {
                    $this->assertCount(4, $group['teams']);
                    $this->assertSame(6, $group['coverage']['published']);
                }
            } elseif ($tab === 'bracket') {
                $rounds = $response->viewData('bracketData')['rounds'];
                $this->assertCount(4, $rounds['QF']);
                $this->assertCount(2, $rounds['SF']);
                $this->assertNotNull($rounds['F'][0]['match']['result']);
            } else {
                $data = $response->viewData('rankingData');
                $this->assertSame(336, $data['coverage']['players_with_published_entries']);
                $this->assertGreaterThanOrEqual(5, count($data['entries']));
                $this->assertLessThanOrEqual(336, count($data['entries']));
            }
        }
        // Five real authenticated accounts, sequentially; not five concurrent writers.
        foreach ($scorers as $scorer) {
            $this->signedIn($scorer);
            foreach (['dashboard', 'groups', 'knockouts'] as $page) {
                [, $metric] = $this->measure('/scorer/'.$page);
                $metrics['scorer_'.$page][] = $metric;
            }
            $setup = app(TournamentGroupService::class)->setup(2026);
            $this->assertCount(7, $setup['groups']);
            $this->assertCount(0, $setup['unassigned_teams']);
            $this->assertSame(42, $setup['expected_matches']);
        }
        $report = [];
        foreach ($metrics as $name => $samples) {
            $times = array_column($samples, 'ms');
            sort($times);
            $report[$name] = ['requests' => count($samples), 'p50_ms' => $times[(int) ceil(count($times) * .5) - 1],
                'p95_ms' => $times[(int) ceil(count($times) * .95) - 1], 'max_ms' => max($times),
                'queries_min' => min(array_column($samples, 'queries')), 'queries_max' => max(array_column($samples, 'queries')),
                'max_bytes' => max(array_column($samples, 'bytes'))];
        }
        fwrite(STDOUT, "\nLOCAL SEQUENTIAL REHEARSAL (not network/concurrent load): ".json_encode($report, JSON_THROW_ON_ERROR)."\n");
    }

    private function measure(string $url): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $start = hrtime(true);
        try {
            $response = $this->get($url)->assertOk();
            $metric = ['ms' => round((hrtime(true) - $start) / 1e6, 2), 'queries' => count(DB::getQueryLog()),
                'bytes' => strlen($response->getContent())];

            return [$response, $metric];
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    private function seedTournament()
    {
        $scorers = User::factory()->count(5)->create(['company_id' => null, 'is_admin' => false]);
        DB::table('users')->whereIn('id', $scorers->pluck('id'))->update(['is_scorer' => true]);
        $teams = Team::factory()->count(28)->create(['approved' => true]);
        $members = [];
        foreach ($teams as $index => $team) {
            foreach (range(0, 11) as $player) {
                $name = 'Synthetic team '.($index + 1).' player '.($player + 1);
                $gender = $player < 3 ? 'Female' : 'Male';
                $id = DB::table('team_members')->insertGetId(['team_id' => $team->id, 'name' => $name, 'gender' => $gender]);
                $members[$team->id][] = compact('id', 'name', 'gender');
            }
        }
        foreach (range(0, 6) as $groupIndex) {
            $group = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'Synthetic group '.($groupIndex + 1)]);
            foreach (range(0, 3) as $position) {
                $index = $groupIndex * 4 + $position;
                DB::table('group_teams')->insert(['tournament_year' => 2026, 'group_id' => $group, 'team_id' => $teams[$index]->id,
                    'qualified' => $position === 0 || $index === 1]);
            }
            foreach (range(0, 2) as $one) {
                foreach (range($one + 1, 3) as $two) {
                    $this->official($teams[$groupIndex * 4 + $one]->id, $teams[$groupIndex * 4 + $two]->id, 'G', $group, null, $members);
                }
            }
        }
        foreach ([[0, 1], [4, 24], [8, 20], [12, 16]] as $slot => [$one, $two]) {
            $this->official($teams[$one]->id, $teams[$two]->id, 'QF', null, $slot + 1, $members);
        }
        $this->official($teams[12]->id, $teams[0]->id, 'SF', null, 1, $members);
        $this->official($teams[4]->id, $teams[8]->id, 'SF', null, 2, $members);
        $this->official($teams[12]->id, $teams[4]->id, 'F', null, 1, $members);
        app(TournamentSummaryRebuildService::class)->rebuild(2026);

        return $scorers;
    }

    private function official(int $one, int $two, string $stage, ?int $group, ?int $slot, array $members): void
    {
        $balls = in_array($stage, ['SF', 'F'], true) ? 6 : 4;
        $match = DB::table('matches')->insertGetId(['tournament_year' => 2026, 'stage' => $stage, 'group_id' => $group,
            'bracket_position' => $slot, 'team1_id' => $one, 'team2_id' => $two, 'balls_per_over' => $balls,
            'status' => 'finished', 'result_published_at' => now(), 'player_stats_published_at' => now(),
            'structure_locked_at' => now(), 'result_type' => 'win', 'result_method' => 'normal', 'winner_id' => $one,
            'batting_first_id' => $one, 'team1_runs' => 100, 'team1_wickets' => 3, 'team1_deliveries' => 5 * $balls,
            'team2_runs' => 90, 'team2_wickets' => 3, 'team2_deliveries' => 5 * $balls]);
        $rows = [];
        foreach ([$one, $two] as $side => $team) {
            foreach ($members[$team] as $index => $member) {
                $rows[] = ['match_id' => $match, 'team_id' => $team, 'team_member_id' => $member['id'],
                    'player_name' => $member['name'], 'player_gender' => $member['gender'], 'record_state' => 'published',
                    'pending_action' => null, 'published_at' => now(), 'runs_scored' => $side === 0 ? 8 + (int) ($index < 4) : 7 + (int) ($index < 6),
                    'balls_faced' => intdiv(5 * $balls, 12) + (int) ($index < 5 * $balls % 12), 'fours' => 1, 'sixes' => 0,
                    'bowling_balls' => $index < 4 ? intdiv(5 * $balls, 4) + (int) ($index < 5 * $balls % 4) : 0,
                    'wickets_taken' => (int) ($index < 3), 'dot_balls' => $index < 4 ? 2 : 0,
                    'runs_conceded' => $index < 4 ? ($side === 0 ? 22 + (int) ($index < 2) : 25) : 0];
            }
        }
        DB::table('player_match_stats')->insert($rows);
    }
}
