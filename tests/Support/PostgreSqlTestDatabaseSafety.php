<?php

namespace Tests\Support;

use Illuminate\Support\Env;
use PDO;
use RuntimeException;

final class PostgreSqlTestDatabaseSafety
{
    public static function connectionName(): string
    {
        return 'pgsql';
    }

    public static function assertEnvironment(): void
    {
        self::assertTarget([
            'driver' => Env::get('DB_CONNECTION'),
            'host' => Env::get('DB_HOST'),
            'port' => Env::get('DB_PORT'),
            'database' => Env::get('DB_DATABASE'),
            'username' => Env::get('DB_USERNAME'),
            'password' => Env::get('DB_PASSWORD'),
            'url' => Env::get('DB_URL'),
        ], Env::get('APP_ENV'), Env::get('HTG_TEST_POSTGRES_RUN_ID'));
    }

    // Validate strings before constructing a DSN or opening any connection.
    public static function assertTarget(array $connection, mixed $environment, mixed $marker): void
    {
        if ($environment !== 'testing'
            || ($connection['driver'] ?? null) !== 'pgsql'
            || ($connection['host'] ?? null) !== '127.0.0.1'
            || ! ctype_digit((string) ($connection['port'] ?? ''))
            || (int) $connection['port'] < 1024 || (int) $connection['port'] > 65535
            || ($connection['database'] ?? null) !== 'htg_scoring_test'
            || ($connection['username'] ?? null) !== 'htg_test'
            || ! is_string($connection['password'] ?? null)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $connection['password'])
            || ! is_string($marker) || ! preg_match('/\A[a-f0-9]{64}\z/', $marker)
            || ! in_array($connection['url'] ?? null, [null, ''], true)
            || array_key_exists('read', $connection) || array_key_exists('write', $connection)
            || array_key_exists('options', $connection)) {
            throw new RuntimeException('Unsafe PostgreSQL test target. Use composer test:postgresql; no database was opened by this check.');
        }

        foreach (['PGOPTIONS', 'PGSERVICE', 'PGSERVICEFILE', 'PGHOSTADDR'] as $variable) {
            if (! in_array(Env::get($variable), [null, ''], true)) {
                throw new RuntimeException('Unsafe PostgreSQL environment override. Use composer test:postgresql.');
            }
        }
    }

    public static function assertConfiguration(array $config): void
    {
        self::assertEnvironment();
        $connection = $config['database']['connections']['pgsql'] ?? [];
        self::assertTarget($connection, $config['app']['env'] ?? null, Env::get('HTG_TEST_POSTGRES_RUN_ID'));
        if (($config['database']['default'] ?? null) !== 'pgsql'
            || ($connection['search_path'] ?? null) !== 'public'
            || ($connection['sslmode'] ?? null) !== 'prefer') {
            throw new RuntimeException('Unsafe PostgreSQL test configuration.');
        }
        TestDatabaseSafety::assertServices($config);
        self::connect($connection);
    }

    public static function connect(array $connection): PDO
    {
        self::assertTarget($connection, Env::get('APP_ENV'), Env::get('HTG_TEST_POSTGRES_RUN_ID'));
        try {
            $pdo = new PDO(
                'pgsql:host=127.0.0.1;port='.$connection['port'].';dbname=htg_scoring_test;connect_timeout=3;sslmode=prefer',
                'htg_test', $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $identity = $pdo->query("SELECT current_database() AS db, current_user AS usr, current_setting('htg_test.run_id', true) AS marker")->fetch(PDO::FETCH_ASSOC);
            if ($identity['db'] !== 'htg_scoring_test' || $identity['usr'] !== 'htg_test'
                || ! hash_equals(Env::get('HTG_TEST_POSTGRES_RUN_ID'), (string) $identity['marker'])) {
                throw new RuntimeException('Test server marker mismatch.');
            }

            return $pdo;
        } catch (\Throwable) {
            // Do not expose connection details or credentials in test output.
            throw new RuntimeException('PostgreSQL test server identity could not be verified. No migrations were allowed.');
        }
    }
}
