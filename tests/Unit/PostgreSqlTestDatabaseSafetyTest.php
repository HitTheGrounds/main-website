<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\PostgreSqlTestDatabaseSafety;

class PostgreSqlTestDatabaseSafetyTest extends TestCase
{
    #[DataProvider('unsafeTargets')]
    public function test_unsafe_targets_are_rejected_without_opening_a_database(array $changes, mixed $environment, mixed $marker): void
    {
        $connection = array_replace([
            'driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => '15432',
            'database' => 'htg_scoring_test', 'username' => 'htg_test',
            'password' => str_repeat('a', 64),
        ], $changes);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe PostgreSQL test target');
        PostgreSqlTestDatabaseSafety::assertTarget($connection, $environment, $marker);
    }

    public static function unsafeTargets(): array
    {
        $marker = str_repeat('b', 64);

        return [
            'production environment' => [[], 'production', $marker],
            'remote host' => [['host' => 'department.example'], 'testing', $marker],
            'socket host' => [['host' => '/tmp'], 'testing', $marker],
            'wrong database' => [['database' => 'application'], 'testing', $marker],
            'wrong user' => [['username' => 'postgres'], 'testing', $marker],
            'default port' => [['port' => '543'], 'testing', $marker],
            'DSN injection' => [['port' => '15432;host=remote'], 'testing', $marker],
            'out of range port' => [['port' => '65536'], 'testing', $marker],
            'missing password' => [['password' => null], 'testing', $marker],
            'missing identity' => [[], 'testing', null],
            'malformed identity' => [[], 'testing', 'test'],
            'URL override' => [['url' => 'pgsql://unsafe'], 'testing', $marker],
            'read override' => [['read' => []], 'testing', $marker],
            'write override' => [['write' => []], 'testing', $marker],
            'PDO options' => [['options' => []], 'testing', $marker],
        ];
    }
}
