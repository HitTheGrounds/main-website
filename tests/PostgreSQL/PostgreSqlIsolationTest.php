<?php

namespace Tests\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class PostgreSqlIsolationTest extends PostgreSqlTestCase
{
    use RefreshDatabase;

    public function test_migrations_run_only_on_the_verified_disposable_database(): void
    {
        $this->assertSame(['pgsql'], array_keys(config('database.connections')));
        $this->assertSame('htg_scoring_test', DB::connection()->getDatabaseName());
        $this->assertTrue(Schema::hasTable('teams'));
        $this->assertFalse(DB::selectOne('SELECT rolsuper FROM pg_roles WHERE rolname = current_user')->rolsuper);
        $this->assertSame('1', DB::selectOne("SELECT ('{\"value\": 1}'::jsonb)->>'value' AS value")->value);
    }

    public function test_independent_connections_contend_for_and_release_a_transaction_lock(): void
    {
        // Separate PDO sessions: Laravel's transaction wrapper must not hide contention.
        $config = config('database.connections.pgsql');
        $first = PostgreSqlTestDatabaseSafety::connect($config);
        $second = PostgreSqlTestDatabaseSafety::connect($config);
        $this->assertNotSame($first->query('SELECT pg_backend_pid()')->fetchColumn(), $second->query('SELECT pg_backend_pid()')->fetchColumn());
        try {
            $first->beginTransaction();
            $second->beginTransaction();
            $this->assertTrue($first->query('SELECT pg_try_advisory_xact_lock(193725, 1)')->fetchColumn());
            $this->assertFalse($second->query('SELECT pg_try_advisory_xact_lock(193725, 1)')->fetchColumn());
            $first->rollBack();
            $this->assertTrue($second->query('SELECT pg_try_advisory_xact_lock(193725, 1)')->fetchColumn());
        } finally {
            foreach ([$first, $second] as $connection) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
            }
        }
    }

    public function test_a_mismatched_server_marker_blocks_application_bootstrap(): void
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$test = new class('probe') extends \Tests\PostgreSQL\PostgreSqlTestCase {};
try {
    $test->createApplication();
    exit(1);
} catch (\RuntimeException $exception) {
    if ($exception->getMessage() === 'PostgreSQL test server identity could not be verified. No migrations were allowed.') {
        echo 'Server mismatch blocked before providers and migrations.';
        exit(0);
    }
    exit(2);
}
PHP;
        $probe = new Process([PHP_BINARY, '-r', $code], base_path(), [
            'HTG_TEST_POSTGRES_RUN_ID' => bin2hex(random_bytes(32)),
        ]);
        $probe->setTimeout(15)->run();
        $this->assertTrue($probe->isSuccessful());
        $this->assertStringContainsString('Server mismatch blocked', $probe->getOutput());
    }
}
