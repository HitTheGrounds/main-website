<?php

namespace Tests\PostgreSQL;

use App\Models\Team;
use App\Services\Tournament\PublicReadSnapshot;
use App\Services\Tournament\PublicTournamentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class PublicReadSnapshotTest extends PostgreSqlTestCase
{
    use DatabaseMigrations;

    public function test_read_only_repeatable_read_and_local_timeouts_leave_session_settings_unchanged(): void
    {
        $before = DB::selectOne("SELECT current_setting('transaction_isolation') AS isolation, current_setting('transaction_read_only') AS read_only, current_setting('statement_timeout') AS statement_timeout, current_setting('lock_timeout') AS lock_timeout");
        $inside = app(PublicReadSnapshot::class)->run(fn () => DB::selectOne("SELECT current_setting('transaction_isolation') AS isolation, current_setting('transaction_read_only') AS read_only, current_setting('statement_timeout') AS statement_timeout, current_setting('lock_timeout') AS lock_timeout"));
        $this->assertSame('repeatable read', $inside->isolation);
        $this->assertSame('on', $inside->read_only);
        $this->assertSame('3s', $inside->statement_timeout);
        $this->assertSame('1s', $inside->lock_timeout);
        $after = DB::selectOne("SELECT current_setting('transaction_isolation') AS isolation, current_setting('transaction_read_only') AS read_only, current_setting('statement_timeout') AS statement_timeout, current_setting('lock_timeout') AS lock_timeout");
        $this->assertEquals($before, $after);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_exception_rolls_back_snapshot_and_restores_connection_settings(): void
    {
        try {
            app(PublicReadSnapshot::class)->run(fn () => throw new RuntimeException('Synthetic read failure'));
            $this->fail('Expected read failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic read failure', $exception->getMessage());
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame('read committed', DB::selectOne('SHOW transaction_isolation')->transaction_isolation);
        $this->assertSame('off', DB::selectOne('SHOW transaction_read_only')->transaction_read_only);
        DB::table('tournament_groups')->insert(['name' => 'Writable after snapshot', 'tournament_year' => 2026]);
        $this->assertDatabaseHas('tournament_groups', ['name' => 'Writable after snapshot']);
    }

    public function test_publication_between_public_queries_cannot_mix_old_standings_with_new_match_coverage(): void
    {
        config(['tournament.enabled' => true]);
        $group = DB::table('tournament_groups')->insertGetId(['name' => 'Snapshot group', 'tournament_year' => 2026]);
        $teams = Team::factory()->count(2)->create(['approved' => true]);
        foreach ($teams as $team) {
            DB::table('group_teams')->insert(['group_id' => $group, 'tournament_year' => 2026, 'team_id' => $team->id]);
        }
        $match = DB::table('matches')->insertGetId(['group_id' => $group, 'stage' => 'G', 'tournament_year' => 2026,
            'team1_id' => $teams[0]->id, 'team2_id' => $teams[1]->id, 'balls_per_over' => 4]);
        $observer = PostgreSqlTestDatabaseSafety::connect(config('database.connections.pgsql'));
        $committed = false;
        DB::listen(function ($query) use ($observer, &$committed, $match, $group, $teams) {
            if ($committed || ! str_contains($query->sql, 'from "group_teams"')) {
                return;
            }
            $committed = true;
            // The reader has already fetched the original group rows. Publish
            // coherent new coverage/standings through an independent connection.
            $observer->beginTransaction();
            try {
                $statement = $observer->prepare("UPDATE matches SET status = 'finished', result_type = 'win', result_method = 'normal', winner_id = ?, result_published_at = CURRENT_TIMESTAMP WHERE id = ?");
                $statement->execute([$teams[0]->id, $match]);
                $statement = $observer->prepare('UPDATE group_teams SET played = 1, points = 2, wins = 1 WHERE group_id = ? AND team_id = ?');
                $statement->execute([$group, $teams[0]->id]);
                $observer->commit();
            } catch (\Throwable $exception) {
                $observer->rollBack();
                throw $exception;
            }
        });
        $service = app(PublicTournamentService::class);
        $old = $service->standings(2026)['groups'][0];
        $this->assertTrue($committed);
        $this->assertSame(0, $old['coverage']['published']);
        $this->assertSame([0, 0], array_column($old['teams'], 'points'));
        $new = $service->standings(2026)['groups'][0];
        $this->assertSame(1, $new['coverage']['published']);
        $this->assertSame([2, 0], array_column($new['teams'], 'points'));
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_statement_timeout_returns_generic_retry_response_and_restores_connection(): void
    {
        try {
            app(PublicReadSnapshot::class)->run(fn () => DB::selectOne('SELECT pg_sleep(4)'));
            $this->fail('Expected bounded public read timeout.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertSame('Tournament data is temporarily unavailable. Please refresh shortly.', $exception->getMessage());
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame('off', DB::selectOne('SHOW transaction_read_only')->transaction_read_only);
        $this->assertSame('0', DB::selectOne('SHOW statement_timeout')->statement_timeout);
    }
}
