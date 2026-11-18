<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicTournamentTablesAndBracketTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tournament.enabled' => true, 'tournament.year' => 2026]);
        Route::middleware('web')->group(base_path('routes/tournament.php'));
        Route::getRoutes()->refreshNameLookups();
    }

    private function group(string $name, int $count = 2, int $year = 2026): array
    {
        $id = DB::table('tournament_groups')->insertGetId(['name' => $name, 'tournament_year' => $year]);
        $teams = Team::factory()->count($count)->create(['approved' => true, 'captain_email' => 'PRIVATE-CONTACT@example.test']);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $id, 'tournament_year' => $year, 'team_id' => $team->id,
                'qualified' => true, 'point_adjustments' => json_encode([['reason' => 'PRIVATE-ADJUSTMENT']])]);
        }

        return [$id, $teams];
    }

    private function match(int $a, int $b, array $changes): int
    {
        return DB::table('matches')->insertGetId(array_replace([
            'tournament_year' => 2026, 'stage' => 'QF', 'team1_id' => $a, 'team2_id' => $b,
            'balls_per_over' => 4, 'result_note' => 'PRIVATE-NOTE',
            'draft_result' => json_encode(['result_note' => 'PRIVATE-DRAFT']),
        ], $changes));
    }

    private function win(int $a, int $b, string $stage, int $slot): int
    {
        return $this->match($a, $b, ['stage' => $stage, 'bracket_position' => $slot, 'status' => 'finished',
            'result_published_at' => now(), 'result_type' => 'win', 'result_method' => 'normal',
            'winner_id' => $a, 'batting_first_id' => $a, 'team1_runs' => 50, 'team2_runs' => 40]);
    }

    public static function groupCounts(): array
    {
        return [[0], [1], [7], [9]];
    }

    #[DataProvider('groupCounts')]
    public function test_group_navigation_and_tables_follow_stored_group_count(int $count): void
    {
        $groups = [];
        for ($index = 1; $index <= $count; $index++) {
            [$id] = $this->group('Public pool '.$index);
            $groups[] = $id;
        }
        $this->group('OTHER-YEAR-GROUP', 2, 2027);
        $response = $this->get('/tournament?tab=table')->assertOk()->assertDontSee('OTHER-YEAR-GROUP')->assertDontSee('PRIVATE-');
        $this->assertCount($count, $response->viewData('tableData')['groups']);
        foreach ($groups as $id) {
            $response->assertSee('href="#group-'.$id.'"', false)->assertSee('id="group-'.$id.'-heading"', false);
        }
        if ($count === 0) {
            $response->assertSee('Groups have not been announced yet.');
        } else {
            $response->assertDontSee('Qualified for the knockouts')->assertSee('(qualified)')->assertSee('overflow-x-auto');
        }
    }

    public function test_table_notices_distinguish_rank_qualification_unknown_nrr_and_coverage(): void
    {
        [$group, $teams] = $this->group('Group <A>', 4);
        foreach ([0, 1, 2, 3] as $index) {
            DB::table('group_teams')->where('team_id', $teams[$index]->id)->update([
                'points' => 4, 'nrr' => $index === 3 ? null : ($index === 2 ? '0.999999' : '1.000000'),
                'qualified' => $index === 2,
            ]);
        }
        $this->group('Smaller group', 2);
        $this->match($teams[0]->id, $teams[1]->id, ['stage' => 'G', 'group_id' => $group]);
        $response = $this->get('/tournament?tab=table')->assertOk()->assertSee('Group <A>')->assertDontSee('Group <A>', false)
            // The per-group "Results & qualification details" panel is gone; qualified teams carry a Q marker.
            ->assertDontSee('Results & qualification details')->assertDontSee('possible group fixtures')
            ->assertSee('Not available')->assertDontSee('Qualified for the knockouts')->assertSee('(qualified)')
            ->assertDontSee('Runner-up comparison')->assertDontSee('How standings are ranked')
            ->assertDontSee('PRIVATE-');
        $rows = $response->viewData('tableData')['groups'][0]['teams'];
        $this->assertSame([1, 1, 3, 4], array_column($rows, 'rank'));
        $this->assertSame([false, false, true, false], array_column($rows, 'qualified'));
    }

    public function test_complete_group_results_do_not_display_incomplete_notice(): void
    {
        [$group, $teams] = $this->group('Complete group');
        DB::table('group_teams')->update(['nrr' => '0.000000']);
        DB::table('group_teams')->where('team_id', $teams[0]->id)->update(['points' => 2]);
        $this->match($teams[0]->id, $teams[1]->id, ['stage' => 'G', 'group_id' => $group, 'status' => 'finished',
            'result_published_at' => now(), 'result_type' => 'no_result']);
        $this->get('/tournament?tab=table')->assertOk()->assertDontSee('Runner-up comparison')
            ->assertDontSee('Group results are incomplete.')->assertDontSee('Not available')
            ->assertSee('0.0000')->assertDontSee('0.000000');
    }

    public function test_empty_group_and_empty_bracket_have_honest_placeholders(): void
    {
        $this->group('Waiting group', 0);
        $this->get('/tournament?tab=table')->assertOk()->assertSee('Teams have not been assigned')
            ->assertDontSee('Runner-up comparison');
        $response = $this->get('/tournament?tab=bracket')->assertOk()->assertDontSee('Choose a knockout round');
        $this->assertSame(7, substr_count($response->getContent(), '>To be confirmed<'));
        $response->assertDontSee('Winner of ')->assertDontSee('A1 vs');
    }

    public function test_bracket_connections_use_actual_winners_and_selected_positions(): void
    {
        [, $teams] = $this->group('Knockout teams', 8);
        $qf4 = $this->win($teams[0]->id, $teams[1]->id, 'QF', 4);
        $qf1 = $this->win($teams[2]->id, $teams[3]->id, 'QF', 1);
        $this->win($teams[4]->id, $teams[5]->id, 'QF', 2);
        $this->win($teams[6]->id, $teams[7]->id, 'QF', 3);
        $sf2 = $this->win($teams[0]->id, $teams[2]->id, 'SF', 2);
        $sf1 = $this->win($teams[4]->id, $teams[6]->id, 'SF', 1);
        $this->match($teams[4]->id, $teams[0]->id, ['stage' => 'F', 'bracket_position' => 1]);
        $response = $this->get('/tournament?tab=bracket')->assertOk()->assertDontSee('PRIVATE-');
        foreach ([$qf4 => 'Quarter-final 4', $qf1 => 'Quarter-final 1', $sf2 => 'Semi-final 2', $sf1 => 'Semi-final 1'] as $id => $label) {
            $response->assertSee('href="#match-'.$id.'-heading">Winner of '.$label.'</a>', false);
        }
        $this->assertSame($qf4, $response->viewData('bracketData')['rounds']['SF'][1]['team1']['source_match_id']);
        $this->assertSame($sf1, $response->viewData('bracketData')['rounds']['F'][0]['team1']['source_match_id']);

        DB::table('matches')->where('id', $qf4)->update(['status' => 'live']);
        $this->get('/tournament?tab=bracket')->assertOk()->assertSee('Previous-round result not confirmed')
            ->assertDontSee('href="#match-'.$qf4.'-heading">Winner of ', false);
    }

    public function test_unpublished_finished_knockouts_remain_unconfirmed(): void
    {
        [, $teams] = $this->group('Knockout teams');
        $id = $this->match($teams[0]->id, $teams[1]->id, ['bracket_position' => 1, 'status' => 'finished']);
        $this->get('/tournament?tab=bracket')->assertOk()->assertSee('To be confirmed')
            ->assertDontSee('match-'.$id.'-heading', false)->assertDontSee($teams[0]->team_name);
    }
}
