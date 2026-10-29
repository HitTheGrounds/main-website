<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\TestDatabaseSafety;

class TestDatabaseSafetyTest extends TestCase
{
    private function safeConfig(): array
    {
        return [
            'app' => ['env' => 'testing'],
            'database' => [
                'default' => 'sqlite',
                'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'url' => null]],
            ],
            'cache' => ['default' => 'array'],
            'session' => ['driver' => 'array'],
            'queue' => ['default' => 'sync'],
            'mail' => ['default' => 'array'],
        ];
    }

    public function test_allows_only_the_expected_isolated_configuration(): void
    {
        TestDatabaseSafety::assertConfiguration($this->safeConfig());
        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('unsafeOverrides')]
    public function test_rejects_unsafe_configuration_before_connecting(string $key, mixed $value): void
    {
        $config = $this->safeConfig();
        data_set($config, $key, $value);

        $this->expectException(RuntimeException::class);
        TestDatabaseSafety::assertConfiguration($config);
    }

    public static function unsafeOverrides(): array
    {
        return [
            'production environment' => ['app.env', 'production'],
            'PostgreSQL default' => ['database.default', 'pgsql'],
            'missing default' => ['database.default', null],
            'wrong driver' => ['database.connections.sqlite.driver', 'pgsql'],
            'application SQLite file' => ['database.connections.sqlite.database', 'database/database.sqlite'],
            'memory URI instead of explicit target' => ['database.connections.sqlite.database', 'file::memory:'],
            'URL override' => ['database.connections.sqlite.url', 'sqlite:///application.sqlite'],
            'read override' => ['database.connections.sqlite.read', ['database' => 'application.sqlite']],
            'write override' => ['database.connections.sqlite.write', ['database' => 'application.sqlite']],
            'persistent cache' => ['cache.default', 'database'],
            'persistent session' => ['session.driver', 'database'],
            'persistent queue' => ['queue.default', 'database'],
            'real mail transport' => ['mail.default', 'smtp'],
        ];
    }

    public function test_missing_configuration_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        TestDatabaseSafety::assertConfiguration([]);
    }

    public function test_cached_configuration_is_rejected_before_it_can_execute(): void
    {
        // Use Laravel's relative cache-path form on both Windows and Linux.
        $cache = tempnam(dirname(__DIR__), '.safety-cache-');
        $this->assertNotFalse($cache);
        file_put_contents($cache, '<?php throw new RuntimeException("CACHED CONFIG EXECUTED");');
        $originalHash = hash_file('sha256', $cache);

        try {
            $code = <<<'PHP'
require $argv[1];
$probe = new class('testProbe') extends \Tests\TestCase {
    public function testProbe(): void {}
};
try {
    $probe->createApplication();
    echo 'BOOTSTRAP_ACCEPTED';
} catch (\RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(2);
}
PHP;
            $root = dirname(__DIR__, 2);
            $process = new Process([PHP_BINARY, '-r', $code, $root.'/tests/bootstrap.php'], $root, [
                'APP_CONFIG_CACHE' => 'tests/'.basename($cache),
            ]);
            $process->run();

            $this->assertSame(2, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
            $this->assertStringContainsString('cached application configuration must not be loaded', $process->getErrorOutput());
            $this->assertStringNotContainsString('CACHED CONFIG EXECUTED', $process->getErrorOutput());
            $this->assertSame($originalHash, hash_file('sha256', $cache));
        } finally {
            unlink($cache);
        }
    }

    #[DataProvider('unsafeEnvironments')]
    public function test_phpunit_refuses_inherited_unsafe_environment(string $key, string $value): void
    {
        $root = dirname(__DIR__, 2);
        $environment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];
        $environment[$key] = $value;
        $process = new Process([
            PHP_BINARY, $root.'/vendor/phpunit/phpunit/phpunit',
            '--configuration', $root.'/phpunit.xml', '--list-tests',
        ], $root, $environment);
        $process->run();

        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('Unsafe test environment', $process->getOutput().$process->getErrorOutput());
        $this->assertStringNotContainsString('Available test', $process->getOutput());
    }

    public static function unsafeEnvironments(): array
    {
        return [
            'non-test environment' => ['APP_ENV', 'production'],
            'network driver' => ['DB_CONNECTION', 'pgsql'],
            'file database' => ['DB_DATABASE', 'unsafe-target.sqlite'],
            'database URL' => ['DB_URL', 'sqlite:///unsafe-target.sqlite'],
        ];
    }
}
