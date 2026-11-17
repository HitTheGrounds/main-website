<?php

namespace Tests\Feature;

use App\Models\GroupTeam;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Services\Tournament\PublicReadSnapshot;
use App\Services\Tournament\PublicTournamentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PublicTournamentServiceTest extends TestCase
{
    use DatabaseMigrations;

    private function fixture(int $count = 2, int $year = 2026): array
    {
        config(['tournament.enabled' => true]);
        request()->attributes->remove('user');
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'Public group '.$year, 'tournament_year' => $year]);
        $teams = Team::factory()->count($count)->create(['approved' => true, 'captain_email' => 'PRIVATE-CONTACT@example.test']);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'tournament_year' => $year, 'team_id' => $team->id]);
        }

        return [$group, $teams];
    }

    private function match(int $group, int $a, int $b, array $changes = []): TournamentMatch
    {
        $id = DB::table('matches')->insertGetId(array_replace(['tournament_year' => 2026, 'stage' => 'G', 'group_id' => $group,
            'team1_id' => $a, 'team2_id' => $b, 'balls_per_over' => 4,
            'draft_result' => json_encode(['team1_runs' => 999, 'result_note' => 'PRIVATE-DRAFT'])], $changes));

        return TournamentMatch::findOrFail($id);
    }

    private function official(array $changes = []): array
    {
        return array_replace(['status' => 'finished', 'result_published_at' => now(), 'result_type' => 'win',
            'result_method' => 'normal', 'team1_runs' => 100, 'team2_runs' => 90, 'team1_wickets' => 3, 'team2_wickets' => 7,
            'team1_deliveries' => 20, 'team2_deliveries' => 14, 'result_note' => 'PRIVATE-OFFICIAL-NOTE'], $changes);
    }

    public function test_guest_reads_explicit_safe_arrays_without_private_selects_or_writes(): void
    {
        [$group, $teams] = $this->fixture();
        $match = $this->match($group, $teams[0]->id, $teams[1]->id, $this->official(['winner_id' => $teams[0]->id, 'batting_first_id' => $teams[0]->id]));
        DB::table('group_teams')->where('team_id', $teams[0]->id)->update(['point_adjustments' => json_encode([['points' => 1, 'reason' => 'PRIVATE-ADJUSTMENT', 'actor_id' => 123]])]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });
        $service = app(PublicTournamentService::class);
        $payload = [$service->matches(2026), $service->standings(2026), $service->bracket(2026)];
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        foreach (['PRIVATE-', 'captain_email', 'captain_phone', 'draft_result', 'point_adjustments', 'created_by', 'updated_by', 'published_by', 'version', 'player_stats_revision'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $selects = implode(' ', $queries);
        foreach (['select *', 'captain_email', 'draft_result', 'point_adjustments', 'result_note', 'nrr_inputs', 'team_members', 'player_match_stats', 'users', 'for update', 'advisory', 'insert ', 'update ', 'delete '] as $private) {
            $this->assertStringNotContainsString($private, $selects);
        }
        $row = $payload[0]['matches'][0];
        $this->assertSame($match->id, $row['id']);
        $this->assertSame(100, $row['result']['team1']['runs']);
        $this->assertSame(['runs' => 90, 'wickets' => 7, 'overs' => 3, 'balls' => 2], $row['result']['team2']);
        $this->assertStringContainsString('10 runs', $row['result']['description']);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertWritableConnection();
    }

    public function test_unfinished_and_reopened_matches_never_expose_retained_official_figures_or_times(): void
    {
        [$group, $teams] = $this->fixture(4);
        foreach (['upcoming', 'live'] as $index => $status) {
            $this->match($group, $teams[0]->id, $teams[$index + 1]->id, $this->official(['status' => $status, 'winner_id' => $teams[0]->id]));
        }
        $this->match($group, $teams[0]->id, $teams[3]->id, ['status' => 'finished']);
        $data = app(PublicTournamentService::class)->matches(2026);
        $this->assertSame(2, $data['pagination']['total']);
        foreach ($data['matches'] as $match) {
            $this->assertNull($match['result']);
            $this->assertArrayNotHasKey('result_published_at', $match);
            $this->assertArrayNotHasKey('team1_runs', $match);
        }
        $this->assertStringNotContainsString('PRIVATE-', json_encode($data));
    }

    public function test_year_filters_and_fixed_page_size_never_mix_editions_or_expand_with_browser_input(): void
    {
        [$group, $teams] = $this->fixture(15);
        foreach ($teams->skip(1) as $team) {
            $this->match($group, $teams[0]->id, $team->id);
        }
        [$other, $otherTeams] = $this->fixture(2, 2027);
        $this->match($other, $otherTeams[0]->id, $otherTeams[1]->id, ['tournament_year' => 2027]);
        $service = app(PublicTournamentService::class);
        $first = $service->matches(2026, ['sort' => 'newest']);
        $second = $service->matches(2026, ['sort' => 'newest', 'page' => '2']);
        $this->assertCount(12, $first['matches']);
        $this->assertCount(2, $second['matches']);
        $this->assertSame(14, $first['pagination']['total']);
        $this->assertSame(2, $first['pagination']['last_page']);
        $this->assertSame([], array_intersect(array_column($first['matches'], 'id'), array_column($second['matches'], 'id')));
        $this->assertSame(0, $service->matches(2026, ['group_id' => $other])['pagination']['total']);
        $this->assertCount(1, $service->matches(2027)['matches']);
        $this->assertSame([], $service->matches(2026, ['page' => 3])['matches']);
    }

    public function test_allowlisted_filters_and_null_last_schedule_order_are_stable(): void
    {
        [$group, $teams] = $this->fixture(4);
        $without = $this->match($group, $teams[0]->id, $teams[1]->id);
        $later = $this->match($group, $teams[0]->id, $teams[2]->id, ['status' => 'live', 'scheduled_at' => '2026-11-20 04:00:00']);
        $earlier = $this->match($group, $teams[0]->id, $teams[3]->id, ['scheduled_at' => '2026-11-20 03:00:00']);
        $service = app(PublicTournamentService::class);
        $this->assertSame([$earlier->id, $later->id, $without->id], array_column($service->matches(2026)['matches'], 'id'));
        $this->assertSame([$later->id], array_column($service->matches(2026, ['status' => 'live', 'stage' => 'G', 'group_id' => $group])['matches'], 'id'));
        $this->assertSame([], $service->matches(2026, ['stage' => 'F'])['matches']);
    }

    public static function invalidFilters(): array
    {
        return [[['sort' => 'id desc; drop table users']], [['stage' => 'ANY']], [['status' => 'draft']], [['group_id' => -1]],
            [['page' => 0]], [['page' => 10001]], [['page' => '1.5']], [['per_page' => 10000]], [['published_by' => 1]]];
    }

    #[DataProvider('invalidFilters')]
    public function test_unapproved_filters_are_rejected_before_reading(array $filters): void
    {
        $this->fixture();
        $this->expectException(ValidationException::class);
        app(PublicTournamentService::class)->matches(2026, $filters);
    }

    public function test_public_standings_preserve_exact_ties_qualification_and_provisional_coverage(): void
    {
        [$group, $teams] = $this->fixture(4);
        foreach (['1.000000', '1.000000', '0.999999', null] as $i => $nrr) {
            DB::table('group_teams')->where('team_id', $teams[$i]->id)->update(['points' => 4, 'nrr' => $nrr, 'qualified' => $i === 2]);
        }
        $state = app(PublicTournamentService::class)->standings(2026);
        $rows = $state['groups'][0]['teams'];
        $this->assertSame([1, 1, 3, 4], array_column($rows, 'rank'));
        $this->assertTrue($rows[2]['qualified']);
        $this->assertSame(6, $state['groups'][0]['coverage']['missing']);
        $this->assertTrue($state['groups'][0]['undefined_nrr']);
        $this->assertTrue($state['groups'][0]['tied']);
        $this->assertCount(2, $state['runner_up_guidance']);
        $this->assertTrue($state['runner_up_guidance'][0]['provisional']);
        $this->assertSame([1, 1], array_column($state['runner_up_guidance'], 'group_rank'));
    }

    public function test_bracket_uses_actual_published_wins_and_suppresses_reopened_winner_and_eligibility_flags(): void
    {
        [$group, $teams] = $this->fixture(4);
        GroupTeam::query()->update(['qualified' => true]);
        $one = $this->match($group, $teams[0]->id, $teams[1]->id, $this->official(['stage' => 'QF', 'group_id' => null, 'bracket_position' => 4, 'winner_id' => $teams[0]->id]));
        $two = $this->match($group, $teams[2]->id, $teams[3]->id, $this->official(['stage' => 'QF', 'group_id' => null, 'bracket_position' => 1, 'winner_id' => $teams[2]->id]));
        $this->match($group, $teams[2]->id, $teams[0]->id, ['stage' => 'SF', 'group_id' => null, 'bracket_position' => 2]);
        $service = app(PublicTournamentService::class);
        $state = $service->bracket(2026);
        $sf = $state['rounds']['SF'][1];
        $this->assertSame($two->id, $sf['team1']['source_match_id']);
        $this->assertSame($one->id, $sf['team2']['source_match_id']);
        $this->assertArrayNotHasKey('eligible', $sf['team1']);
        $this->assertNull($state['rounds']['F'][0]['match']);
        $this->assertSame('To be confirmed', $state['rounds']['F'][0]['label']);
        DB::table('matches')->where('id', $one->id)->update(['status' => 'live']);
        $state = $service->bracket(2026);
        $this->assertNull($state['rounds']['SF'][1]['team2']['source_match_id']);
        $this->assertNull($state['rounds']['QF'][3]['match']['result']);
    }

    public function test_read_only_snapshot_rolls_back_and_restores_connection_after_failure(): void
    {
        $this->fixture();
        try {
            app(PublicReadSnapshot::class)->run(fn () => DB::table('tournament_groups')->insert(['name' => 'Must not write', 'tournament_year' => 2026]));
            $this->fail('Expected database read-only enforcement.');
        } catch (QueryException) {
            $this->assertSame(0, DB::transactionLevel());
        }
        $this->assertWritableConnection();
        $this->assertDatabaseMissing('tournament_groups', ['name' => 'Must not write']);
        DB::table('tournament_groups')->insert(['name' => 'Connection restored', 'tournament_year' => 2026]);
        DB::beginTransaction();
        try {
            app(PublicReadSnapshot::class)->run(fn () => null);
            $this->fail('A nested public snapshot must be rejected.');
        } catch (LogicException) {
            $this->assertSame(1, DB::transactionLevel());
        } finally {
            DB::rollBack();
        }
    }

    public function test_feature_gate_and_year_validation_precede_database_snapshot(): void
    {
        $this->fixture();
        foreach ([0, 10000] as $year) {
            try {
                app(PublicTournamentService::class)->matches($year);
                $this->fail('Invalid year must fail.');
            } catch (ValidationException) {
                $this->assertSame(0, DB::transactionLevel());
            }
        }
        config(['tournament.enabled' => false]);
        $this->expectException(HttpException::class);
        app(PublicTournamentService::class)->standings(2026);
    }

    public function test_snapshot_ceiling_fails_closed_without_truncating_groups_or_changing_connection(): void
    {
        $this->fixture();
        foreach (range(1, 128) as $i) {
            DB::table('tournament_groups')->insert(['name' => 'Safety '.$i, 'tournament_year' => 2026]);
        }
        try {
            app(PublicTournamentService::class)->matches(2026);
            $this->fail('Expected bounded snapshot rejection.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }
        $this->assertSame(129, DB::table('tournament_groups')->count());
        $this->assertWritableConnection();
    }

    public function test_empty_edition_returns_honest_empty_data_and_all_tbc_bracket_positions(): void
    {
        config(['tournament.enabled' => true]);
        $service = app(PublicTournamentService::class);
        $this->assertSame([], $service->matches(2040)['matches']);
        $this->assertSame(0, $service->matches(2040)['pagination']['total']);
        $this->assertSame([], $service->standings(2040)['groups']);
        $this->assertSame([], $service->standings(2040)['runner_up_guidance']);
        foreach ($service->bracket(2040)['rounds'] as $stage => $positions) {
            $this->assertCount(config('tournament.knockout_slots.'.$stage), $positions);
            foreach ($positions as $position) {
                $this->assertNull($position['match']);
                $this->assertNull($position['team1']);
                $this->assertNull($position['team2']);
            }
        }
    }

    public function test_unknown_and_zero_innings_stay_distinct_and_six_ball_matches_use_their_own_format(): void
    {
        [$group, $teams] = $this->fixture(4);
        $this->match($group, $teams[0]->id, $teams[1]->id, $this->official(['winner_id' => $teams[0]->id, 'balls_per_over' => 6]));
        $this->match($group, $teams[0]->id, $teams[2]->id, $this->official(['result_type' => 'tie', 'winner_id' => null,
            'team1_runs' => 0, 'team2_runs' => 0, 'team1_wickets' => 0, 'team2_wickets' => 0, 'team1_deliveries' => 0, 'team2_deliveries' => 0]));
        $this->match($group, $teams[0]->id, $teams[3]->id, $this->official(['result_type' => 'no_result', 'result_method' => null,
            'team1_runs' => null, 'team2_runs' => null, 'team1_wickets' => null, 'team2_wickets' => null, 'team1_deliveries' => null, 'team2_deliveries' => null]));
        $matches = app(PublicTournamentService::class)->matches(2026)['matches'];
        $this->assertSame(6, $matches[0]['balls_per_over']);
        $this->assertSame(3, $matches[0]['result']['team1']['overs']);
        $this->assertSame(2, $matches[0]['result']['team1']['balls']);
        $this->assertSame(['runs' => 0, 'wickets' => 0, 'overs' => 0, 'balls' => 0], $matches[1]['result']['team1']);
        $this->assertSame(['runs' => null, 'wickets' => null, 'overs' => null, 'balls' => null], $matches[2]['result']['team1']);
        $this->assertSame('No result.', $matches[2]['result']['description']);
    }

    private function assertWritableConnection(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame(0, (int) DB::selectOne('PRAGMA query_only')->query_only);
        } else {
            $this->assertSame('off', DB::selectOne('SHOW transaction_read_only')->transaction_read_only);
        }
    }
}
