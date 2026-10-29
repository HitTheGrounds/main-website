<?php

namespace Tests\Support;

use Illuminate\Support\Env;
use RuntimeException;

final class TestDatabaseSafety
{
    public static function connectionName(): string
    {
        return 'sqlite';
    }

    public static function assertEnvironment(): void
    {
        if (Env::get('APP_ENV') !== 'testing'
            || Env::get('DB_CONNECTION') !== 'sqlite'
            || Env::get('DB_DATABASE') !== ':memory:'
            || ! in_array(Env::get('DB_URL'), [null, ''], true)) {
            throw new RuntimeException('Unsafe test environment: require APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, and no DB_URL. No database was opened by this check.');
        }
    }

    public static function assertConfigurationCacheIsAbsent(string $path): void
    {
        if (file_exists($path)) {
            throw new RuntimeException('Unsafe test setup: cached application configuration must not be loaded. Use an uncached isolated checkout; the cache has not been read or deleted.');
        }
    }

    public static function assertConfiguration(array $config): void
    {
        $database = $config['database'] ?? [];
        $connection = $database['connections']['sqlite'] ?? [];

        if (($config['app']['env'] ?? null) !== 'testing'
            || ($database['default'] ?? null) !== 'sqlite'
            || ($connection['driver'] ?? null) !== 'sqlite'
            || ($connection['database'] ?? null) !== ':memory:'
            || ! in_array($connection['url'] ?? null, [null, ''], true)
            || array_key_exists('read', $connection)
            || array_key_exists('write', $connection)) {
            throw new RuntimeException('Unsafe test database configuration: only a testing SQLite :memory: connection without URL or read/write overrides is allowed.');
        }

        self::assertServices($config);
    }

    public static function assertServices(array $config): void
    {
        if (($config['cache']['default'] ?? null) !== 'array'
            || ($config['session']['driver'] ?? null) !== 'array'
            || ($config['queue']['default'] ?? null) !== 'sync'
            || ($config['mail']['default'] ?? null) !== 'array') {
            throw new RuntimeException('Unsafe test services: require array cache/session/mail and a sync queue.');
        }
    }
}
