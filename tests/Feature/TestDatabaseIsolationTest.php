<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestDatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_database_uses_only_an_in_memory_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(['sqlite'], array_keys(config('database.connections')));
        $this->assertSame(':memory:', $this->app['db']->connection()->getDatabaseName());
        $this->assertSame('', $this->app['db']->connection()->selectOne('PRAGMA database_list')->file);
        $this->assertTrue($this->app['db']->connection()->getSchemaBuilder()->hasTable('teams'));
    }

    public function test_secondary_connections_are_rejected_before_refresh_traits(): void
    {
        config(['database.connections.guard_probe' => [
            'driver' => 'sqlite',
            'database' => 'unsafe-target.sqlite',
        ]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('secondary database connections are not allowed');
        $this->setUpTraits();
    }
}
