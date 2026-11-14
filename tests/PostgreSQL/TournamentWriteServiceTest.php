<?php

namespace Tests\PostgreSQL;

use App\Services\Tournament\TournamentWriteService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class TournamentWriteServiceTest extends PostgreSqlTestCase
{
    // Real commits, so independent sessions can observe lock lifetime and writes.
    use DatabaseMigrations;

    private function other(): \PDO
    {
        return PostgreSqlTestDatabaseSafety::connect(config('database.connections.pgsql'));
    }

    private function tryLock(\PDO $other, int $year): bool
    {
        $statement = $other->prepare('SELECT pg_try_advisory_xact_lock(?, ?)');
        $statement->execute([TournamentWriteService::LOCK_NAMESPACE, $year]);

        return (bool) $statement->fetchColumn();
    }

    private function worker(): string
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
try {
    $test = new class('probe') extends \Tests\PostgreSQL\PostgreSqlTestCase {};
    $test->createApplication();
    app(\App\Services\Tournament\TournamentWriteService::class)->run(2026, function () {
        $count = \Illuminate\Support\Facades\DB::table('tournament_groups')->where('tournament_year', 2026)->count();
        \Illuminate\Support\Facades\DB::table('tournament_groups')->insert(['tournament_year' => 2026, 'name' => 'Worker saw '.$count]);
    });
    echo 'WROTE';
} catch (\Illuminate\Validation\ValidationException $exception) {
    echo isset($exception->errors()['tournament']) ? 'RETRY' : 'UNEXPECTED VALIDATION';
} catch (\Throwable) {
    echo 'UNEXPECTED WORKER FAILURE';
    exit(2);
}
PHP;
        $process = new Process([PHP_BINARY, '-r', $code], base_path());
        $process->setTimeout(15)->run();
        $this->assertTrue($process->isSuccessful(), 'Guarded worker failed.');

        return trim($process->getOutput());
    }

    public function test_exclusive_year_lock_is_stable_and_other_years_remain_available(): void
    {
        $other = $this->other();
        $result = app(TournamentWriteService::class)->run(2026, function () use ($other) {
            $this->assertFalse($this->tryLock($other, 2026));
            $this->assertTrue($this->tryLock($other, 2027));
            $this->assertSame('read committed', DB::selectOne('SHOW transaction_isolation')->transaction_isolation);
            $this->assertSame('3s', DB::selectOne('SHOW lock_timeout')->lock_timeout);

            return 42;
        });
        $this->assertSame(42, $result);
        $this->assertTrue($this->tryLock($other, 2026));
        $this->assertSame('0', DB::selectOne('SHOW lock_timeout')->lock_timeout);
    }

    public function test_callback_failure_rolls_back_rows_and_releases_the_lock(): void
    {
        $calls = 0;
        try {
            app(TournamentWriteService::class)->run(2026, function () use (&$calls) {
                $calls++;
                DB::table('tournament_groups')->insert(['tournament_year' => 2026, 'name' => 'Rollback']);
                throw new RuntimeException('Synthetic failure');
            });
            $this->fail('Expected callback failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic failure', $exception->getMessage());
        }
        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('tournament_groups', 0);
        $this->assertTrue($this->tryLock($this->other(), 2026));
    }

    public function test_same_year_worker_gets_retry_then_reads_committed_predecessor(): void
    {
        app(TournamentWriteService::class)->run(2026, function () {
            DB::table('tournament_groups')->insert(['tournament_year' => 2026, 'name' => 'Predecessor']);
            $this->assertSame('RETRY', $this->worker());
            $this->assertDatabaseCount('tournament_groups', 1);
        });
        $this->assertSame('WROTE', $this->worker());
        $this->assertDatabaseHas('tournament_groups', ['name' => 'Worker saw 1']);
    }

    public function test_nested_helper_keeps_lock_until_outer_transaction_ends(): void
    {
        $other = $this->other();
        DB::beginTransaction();
        try {
            app(TournamentWriteService::class)->run(2026, fn () => null);
            $this->assertFalse($this->tryLock($other, 2026));
            $this->assertSame('0', DB::selectOne('SHOW lock_timeout')->lock_timeout);
        } finally {
            DB::rollBack();
        }
        $this->assertTrue($this->tryLock($other, 2026));
    }

    public function test_nested_repeatable_read_is_rejected_without_running_callback(): void
    {
        DB::beginTransaction();
        try {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            try {
                app(TournamentWriteService::class)->run(2026, fn () => $this->fail('Unsafe isolation reached callback.'));
                $this->fail('Expected isolation rejection.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('read-committed', $exception->getMessage());
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_row_lock_timeout_rolls_back_callback_changes_with_retry_message(): void
    {
        $id = DB::table('tournament_groups')->insertGetId(['tournament_year' => 2026, 'name' => 'Locked']);
        $other = $this->other();
        $other->beginTransaction();
        $other->query('SELECT id FROM tournament_groups WHERE id = '.(int) $id.' FOR UPDATE');
        try {
            try {
                app(TournamentWriteService::class)->run(2026, function () use ($id) {
                    DB::table('tournament_groups')->insert(['tournament_year' => 2026, 'name' => 'Must roll back']);
                    DB::table('tournament_groups')->where('id', $id)->update(['name' => 'Changed']);
                });
                $this->fail('Expected row lock timeout.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('try again', $exception->errors()['tournament'][0]);
            }
        } finally {
            $other->rollBack();
        }
        $this->assertDatabaseCount('tournament_groups', 1);
        $this->assertDatabaseHas('tournament_groups', ['id' => $id, 'name' => 'Locked']);
        $this->assertTrue($this->tryLock($other, 2026));
    }
}
