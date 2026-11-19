<?php

namespace Tests\Support;

use App\Models\GroupTeam;
use App\Models\PlayerMatchStat;
use App\Models\PlayerTournamentStat;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

trait TournamentSchemaChecks
{
    public function test_additive_migrations_preserve_existing_rows_and_historical_columns(): void
    {
        // This class uses RefreshDatabase on a guarded synthetic target only.
        // Remove just the new schema to recreate an installation awaiting this feature.
        $paths = array_merge(glob(database_path('migrations/2026_11_01_*.php')), glob(database_path('migrations/2026_11_14_*.php')));
        sort($paths);
        foreach (array_reverse($paths) as $path) {
            (require $path)->down();
            DB::table('migrations')->where('migration', basename($path, '.php'))->delete();
        }
        Schema::table('team_members', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
        });
        Schema::table('teams', function (Blueprint $table) {
            $table->string('captain_email')->nullable()->change();
            $table->string('captain_phone')->nullable()->change();
        });
        $team = Team::factory()->create(['captain_email' => null, 'captain_phone' => null, 'approved' => true]);
        $member = TeamMember::factory()->create(['team_id' => $team->id, 'name' => 'Test ක්‍රීඩකයා', 'gender' => 'Other']);
        DB::table('team_members')->where('id', $member->id)->update(['email' => 'historical@example.test', 'phone' => '000123']);
        // An existing administrator must not receive scorer permission implicitly.
        User::factory()->create(['is_admin' => true, 'jwt_token' => 'synthetic-existing-token']);
        DB::table('sessions')->insert(['id' => 'preservation-session', 'payload' => 'synthetic-payload', 'last_activity' => 123]);
        DB::table('cache')->insert(['key' => 'preservation-key', 'value' => 'synthetic-value', 'expiration' => 123]);

        $baseline = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            $columns = Schema::getColumnListing($name);
            $rows = DB::table($name)->select($columns)->get()->map(fn ($row) => (array) $row)->all();
            $baseline[$name] = ['columns' => Schema::getColumns($name), 'names' => $columns, 'rows' => $rows, 'indexes' => Schema::getIndexes($name), 'foreign_keys' => Schema::getForeignKeys($name)];
        }
        $relativePaths = array_map(fn ($path) => 'database/migrations/'.basename($path), $paths);
        $this->artisan('migrate', ['--path' => $relativePaths, '--force' => true])->assertExitCode(0);
        foreach ($baseline as $name => $before) {
            $afterColumns = array_values(array_filter(Schema::getColumns($name), fn ($column) => $name !== 'users' || $column['name'] !== 'is_scorer'));
            $this->assertSame($before['columns'], $afterColumns, $name.' original column definitions');
            $this->assertSame($before['indexes'], Schema::getIndexes($name), $name.' original indexes');
            $this->assertSame($before['foreign_keys'], Schema::getForeignKeys($name), $name.' original foreign keys');
            $query = DB::table($name)->select($before['names']);
            if ($name === 'migrations') {
                $query->whereIn('id', array_column($before['rows'], 'id'));
            }
            $after = $query->get()->map(fn ($row) => (array) $row)->all();
            $this->assertEqualsCanonicalizing($before['rows'], $after, $name.' original values');
        }
        $this->assertSame(count($baseline['migrations']['rows']) + count($paths), DB::table('migrations')->count());
        $this->assertSame(0, DB::table('users')->where('is_scorer', true)->count());
        foreach (['tournament_groups', 'group_teams', 'matches', 'player_match_stats', 'player_tournament_stats'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }

    private function tournamentFixture(): array
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();
        $player = TeamMember::factory()->create(['team_id' => $first->id, 'gender' => 'Other']);
        $group = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'A']);
        $match = DB::table('matches')->insertGetId([
            'tournament_year' => 2026, 'stage' => 'G', 'group_id' => $group,
            'team1_id' => $first->id, 'team2_id' => $second->id, 'balls_per_over' => 4,
        ]);

        return compact('first', 'second', 'player', 'group', 'match');
    }

    private function playerRow(array $fixture): array
    {
        return [
            'match_id' => $fixture['match'], 'team_id' => $fixture['first']->id,
            'team_member_id' => $fixture['player']->id,
            'player_name' => 'Test Player', 'player_gender' => 'Other',
        ];
    }

    private function summaryRow(array $fixture): array
    {
        return [
            'tournament_year' => 2026, 'team_id' => $fixture['first']->id,
            'team_member_id' => $fixture['player']->id,
            'player_name' => 'Test Player', 'player_gender' => 'Other',
        ];
    }

    // A savepoint keeps a rejected PostgreSQL statement from aborting the test transaction.
    private function assertRejected(callable $write): void
    {
        try {
            DB::transaction($write);
        } catch (QueryException $exception) {
            $this->assertContains(substr((string) $exception->getCode(), 0, 2), ['23', '22']);

            return;
        }
        $this->fail('The database accepted a prohibited write.');
    }

    public function test_tournament_defaults_null_zero_and_model_relationships(): void
    {
        $f = $this->tournamentFixture();
        $assignment = DB::table('group_teams')->insertGetId([
            'tournament_year' => 2026, 'group_id' => $f['group'], 'team_id' => $f['first']->id,
        ]);
        DB::table('player_match_stats')->insert($this->playerRow($f));
        DB::table('player_tournament_stats')->insert($this->summaryRow($f));
        $match = TournamentMatch::findOrFail($f['match']);
        $this->assertSame('upcoming', $match->status);
        $this->assertSame(5, $match->overs_per_innings);
        $this->assertSame(7, $match->max_wickets);
        $this->assertSame(1, $match->version);
        $this->assertSame(0, $match->player_stats_revision);
        $this->assertNull($match->team1_runs);
        $this->assertNull($match->result_published_at);
        $this->assertTrue($match->team1->is($f['first']));
        $this->assertTrue($match->team2->is($f['second']));
        $this->assertSame($f['group'], $match->group->id);
        $this->assertCount(1, TournamentGroup::findOrFail($f['group'])->assignments);
        $groupTeam = GroupTeam::findOrFail($assignment);
        $this->assertFalse($groupTeam->qualified);
        $this->assertSame([], $groupTeam->point_adjustments);
        $this->assertSame('0.00000000', $groupTeam->nrr_overs_faced);
        $this->assertNull($groupTeam->nrr);
        foreach (['played', 'wins', 'losses', 'ties', 'no_results', 'base_points', 'points', 'runs_for', 'runs_against'] as $metric) {
            $this->assertSame(0, $groupTeam->$metric);
        }
        $stat = PlayerMatchStat::firstOrFail();
        $this->assertSame('draft', $stat->record_state);
        $this->assertSame('replace', $stat->pending_action);
        $this->assertNull($stat->runs_scored);
        $this->assertTrue($stat->teamMember->is($f['player']));
        $this->assertTrue($stat->tournamentMatch->is($match));
        $stat->runs_scored = 0;
        $stat->save();
        $this->assertSame(0, $stat->fresh()->runs_scored);
        $total = PlayerTournamentStat::firstOrFail();
        $this->assertNull($total->runs_scored);
        $this->assertSame(0, $total->runs_scored_recorded_count);
        $total->dot_balls = 0;
        $total->dot_balls_recorded_count = 1;
        $total->save();
        $this->assertNotNull($total->fresh()->updated_at);
        $this->assertFalse(Schema::hasColumn('player_tournament_stats', 'created_at'));
        $this->assertFalse(User::firstOrFail()->is_scorer);
        $this->assertFalse((new User)->isFillable('is_scorer'));
    }

    public function test_groups_are_dynamic_and_membership_is_year_scoped(): void
    {
        $f = $this->tournamentFixture();
        foreach (range(2, 9) as $number) {
            DB::table('tournament_groups')->insert(['tournament_year' => 2026, 'name' => 'Group '.$number]);
        }
        foreach (range(1, 6) as $number) {
            $team = Team::factory()->create();
            DB::table('group_teams')->insert(['tournament_year' => 2026, 'group_id' => $f['group'], 'team_id' => $team->id]);
        }
        $this->assertSame(9, TournamentGroup::count());
        $this->assertSame(6, TournamentGroup::findOrFail($f['group'])->assignments()->count());
        $next = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2027, 'name' => 'A']);
        $row = ['tournament_year' => 2026, 'group_id' => $f['group'], 'team_id' => $f['first']->id];
        DB::table('group_teams')->insert($row);
        $this->assertRejected(fn () => DB::table('group_teams')->insert($row));
        $this->assertRejected(fn () => DB::table('group_teams')->insert(array_replace($row, ['tournament_year' => 2027])));
        $this->assertRejected(fn () => DB::table('tournament_groups')->insert(['tournament_year' => 2026, 'name' => 'A']));
        DB::table('group_teams')->insert(array_replace($row, ['tournament_year' => 2027, 'group_id' => $next]));
        $this->assertSame(2, $f['first']->groupAssignments()->count());
    }

    public function test_match_pairing_and_knockout_slot_indexes(): void
    {
        $f = $this->tournamentFixture();
        $row = (array) DB::table('matches')->find($f['match']);
        unset($row['id']);
        $this->assertRejected(fn () => DB::table('matches')->insert($row));
        $reversed = array_replace($row, ['team1_id' => $row['team2_id'], 'team2_id' => $row['team1_id']]);
        $this->assertRejected(fn () => DB::table('matches')->insert($reversed));
        $otherGroup = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'B']);
        DB::table('matches')->insert(array_replace($row, ['group_id' => $otherGroup]));
        $nextGroup = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2027, 'name' => 'A']);
        $this->assertRejected(fn () => DB::table('matches')->insert(array_replace($row, ['group_id' => $nextGroup])));
        DB::table('matches')->insert(array_replace($row, ['tournament_year' => 2027, 'group_id' => $nextGroup]));
        foreach (['QF' => 4, 'SF' => 2, 'F' => 1] as $stage => $slots) {
            foreach (range(1, $slots) as $slot) {
                $knockout = array_replace($row, ['stage' => $stage, 'group_id' => null, 'bracket_position' => $slot]);
                DB::table('matches')->insert($knockout);
                $this->assertRejected(fn () => DB::table('matches')->insert($knockout));
            }
        }
        $this->assertSame(10, TournamentMatch::count());
    }

    #[DataProvider('invalidPlayerRows')]
    public function test_player_source_constraints(array $changes): void
    {
        $f = $this->tournamentFixture();
        $this->assertRejected(fn () => DB::table('player_match_stats')->insert(array_replace($this->playerRow($f), $changes)));
    }

    public static function invalidPlayerRows(): array
    {
        return [
            'negative runs' => [['runs_scored' => -1]],
            'out of range runs' => [['runs_scored' => 2147483648]],
            'excess wickets' => [['wickets_taken' => 8]],
            'negative balls' => [['bowling_balls' => -1]],
            'fractional balls' => [['balls_faced' => '1.5']],
            'too many dots' => [['dot_balls' => 5, 'bowling_balls' => 4]],
            'boundaries exceed runs' => [['runs_scored' => 9, 'fours' => 1, 'sixes' => 1]],
            'invalid state' => [['record_state' => 'public']],
            'invalid gender' => [['player_gender' => 'Unknown']],
            'invalid action' => [['pending_action' => 'delete']],
            'draft with no action' => [['pending_action' => null]],
            'draft with timestamp' => [['published_at' => '2026-11-14 10:00:00']],
            'published with pending action' => [['record_state' => 'published', 'published_at' => '2026-11-14 10:00:00']],
            'published without timestamp' => [['record_state' => 'published', 'pending_action' => null]],
            'zero version' => [['version' => 0]],
            'missing match' => [['match_id' => 999999]],
            'missing player' => [['team_member_id' => 999999]],
            'missing team' => [['team_id' => 999999]],
            'missing publisher' => [['record_state' => 'published', 'pending_action' => null, 'published_at' => '2026-11-14 10:00:00', 'published_by' => 999999]],
        ];
    }

    public function test_published_and_draft_rows_coexist_and_history_is_restricted(): void
    {
        $f = $this->tournamentFixture();
        $actor = User::factory()->create();
        $row = $this->playerRow($f);
        DB::table('player_match_stats')->insert($row);
        $this->assertRejected(fn () => DB::table('player_match_stats')->insert($row));
        DB::table('player_match_stats')->insert(array_replace($row, [
            'record_state' => 'published', 'pending_action' => null,
            'published_at' => '2026-11-14 10:00:00', 'published_by' => $actor->id, 'updated_by' => $actor->id,
        ]));
        DB::table('matches')->where('id', $f['match'])->update(['published_by' => $actor->id]);
        $actor->delete();
        $this->assertNull(PlayerMatchStat::where('record_state', 'published')->firstOrFail()->published_by);
        $this->assertNull(TournamentMatch::findOrFail($f['match'])->published_by);
        $this->assertSame(2, $f['player']->playerMatchStats()->count());
        foreach (['matches' => $f['match'], 'teams' => $f['first']->id, 'team_members' => $f['player']->id, 'tournament_groups' => $f['group']] as $table => $id) {
            $this->assertRejected(fn () => DB::table($table)->where('id', $id)->delete());
            $this->assertRejected(fn () => DB::table($table)->where('id', $id)->update(['id' => 999999]));
        }
    }

    #[DataProvider('invalidSummaryRows')]
    public function test_cumulative_constraints(array $changes): void
    {
        $f = $this->tournamentFixture();
        $this->assertRejected(fn () => DB::table('player_tournament_stats')->insert(array_replace($this->summaryRow($f), $changes)));
    }

    public static function invalidSummaryRows(): array
    {
        return [
            'year zero' => [['tournament_year' => 0]],
            'unknown total with coverage' => [['runs_scored_recorded_count' => 1]],
            'known zero without coverage' => [['dot_balls' => 0]],
            'negative coverage' => [['fours_recorded_count' => -1]],
            'negative total' => [['sixes' => -1, 'sixes_recorded_count' => 1]],
            'highest without runs' => [['highest_score' => 1]],
            'runs without highest' => [['runs_scored' => 10, 'runs_scored_recorded_count' => 1]],
            'highest exceeds total' => [['runs_scored' => 10, 'runs_scored_recorded_count' => 1, 'highest_score' => 11]],
            'match without highest' => [['highest_score_match_id' => 999999]],
            'missing player' => [['team_member_id' => 999999]],
            'missing team' => [['team_id' => 999999]],
        ];
    }

    public function test_large_totals_independent_coverage_json_and_decimal_storage(): void
    {
        $f = $this->tournamentFixture();
        DB::table('player_tournament_stats')->insert(array_replace($this->summaryRow($f), [
            'runs_scored' => 4294967296, 'runs_scored_recorded_count' => 3,
            'highest_score' => 2147483647, 'highest_score_match_id' => $f['match'],
            'dot_balls' => 10, 'dot_balls_recorded_count' => 2,
            'bowling_balls' => 5, 'bowling_balls_recorded_count' => 1,
        ]));
        $total = PlayerTournamentStat::firstOrFail();
        $this->assertSame(4294967296, $total->runs_scored);
        $this->assertSame(10, $total->dot_balls);
        $this->assertSame($f['match'], $total->highestScoreMatch->id);
        $this->assertRejected(fn () => DB::table('player_tournament_stats')->insert($this->summaryRow($f)));
        $this->assertRejected(fn () => DB::table('matches')->where('id', $f['match'])->delete());
        DB::table('group_teams')->insert([
            'tournament_year' => 2026, 'group_id' => $f['group'], 'team_id' => $f['first']->id,
            'nrr_overs_faced' => '3.25000000', 'nrr' => '-0.125000',
            'point_adjustments' => json_encode([['points' => -1, 'reason' => 'Committee adjustment']]),
        ]);
        $assignment = GroupTeam::firstOrFail();
        $this->assertSame('3.25000000', $assignment->nrr_overs_faced);
        $this->assertSame('-0.125000', $assignment->nrr);
        $this->assertSame(-1, $assignment->point_adjustments[0]['points']);
        DB::table('matches')->where('id', $f['match'])->update(['draft_result' => '{"team1_runs":0}', 'nrr_inputs' => '{"team1":{"runs":10}}']);
        $match = TournamentMatch::findOrFail($f['match']);
        $this->assertSame(['team1_runs' => 0], $match->draft_result);
        $this->assertSame(['team1' => ['runs' => 10]], $match->nrr_inputs);
        $indexes = Schema::getIndexes('player_tournament_stats');
        foreach (['runs_scored', 'wickets_taken', 'sixes', 'fours', 'dot_balls', 'highest_score'] as $metric) {
            $this->assertContains('pts_'.$metric.'_ranking_idx', array_column($indexes, 'name'));
        }
        if (DB::getDriverName() === 'pgsql') {
            $this->assertSame('jsonb', DB::selectOne('SELECT pg_typeof(point_adjustments)::text AS type FROM group_teams LIMIT 1')->type);
        }
    }

    public function test_each_metric_enforces_coverage_and_source_bounds(): void
    {
        $f = $this->tournamentFixture();
        foreach (['runs_scored', 'balls_faced', 'fours', 'sixes', 'bowling_balls', 'runs_conceded', 'wickets_taken', 'dot_balls'] as $metric) {
            $this->assertRejected(fn () => DB::table('player_match_stats')->insert(array_replace($this->playerRow($f), [$metric => -1])));
            $this->assertRejected(fn () => DB::table('player_tournament_stats')->insert(array_replace($this->summaryRow($f), [$metric => 0])));
            $this->assertRejected(fn () => DB::table('player_tournament_stats')->insert(array_replace($this->summaryRow($f), [$metric.'_recorded_count' => 1])));
        }
        $this->assertRejected(fn () => DB::table('player_tournament_stats')->insert(array_replace($this->summaryRow($f), [
            'runs_scored' => 1, 'runs_scored_recorded_count' => 1, 'highest_score' => 1, 'highest_score_match_id' => 999999,
        ])));
        DB::table('player_match_stats')->insert(array_replace($this->playerRow($f), ['wickets_taken' => 7, 'runs_scored' => 0]));
        $this->assertSame(7, PlayerMatchStat::firstOrFail()->wickets_taken);
        DB::table('player_tournament_stats')->insert($this->summaryRow($f));
        DB::table('player_tournament_stats')->insert(array_replace($this->summaryRow($f), ['tournament_year' => 2027]));
        $this->assertSame(2, $f['player']->playerTournamentStats()->count());
    }
}
