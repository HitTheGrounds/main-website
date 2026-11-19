<?php

// This runner does not boot Laravel or load the application's .env.
require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\Process\Process;

chdir(dirname(__DIR__));

function dockerCommand(array $arguments, array $environment = [], int $timeout = 120, ?string $input = null): string
{
    $process = new Process(['docker', ...$arguments], null, $environment, $input, $timeout);
    $process->run();
    if (! $process->isSuccessful()) {
        // Docker's raw output may contain environment values. Keep errors generic.
        throw new RuntimeException('Docker command failed: '.$arguments[0].'. Check that the local Linux Docker engine is available.');
    }

    return trim($process->getOutput());
}

$container = null;
$runId = bin2hex(random_bytes(32));
$exitCode = 1;
$cleanup = function () use (&$container, $runId): void {
    if ($container === null) {
        return;
    }
    try {
        $owner = dockerCommand(['inspect', '--format', '{{index .Config.Labels "htg.scoring-test-run"}}', $container]);
        if (! hash_equals($runId, $owner)) {
            throw new RuntimeException('Container ownership check failed.');
        }
        dockerCommand(['rm', '--force', '--volumes', $container]);
        $container = null;
        echo "Disposable PostgreSQL container removed.\n";
    } catch (Throwable) {
        fwrite(STDERR, "Cleanup failed. Inspect containers with label htg.scoring-test-run; remove only this run's container.\n");
    }
};
register_shutdown_function($cleanup);

try {
    $arguments = array_slice($argv, 1);
    if ($arguments !== [] && (count($arguments) !== 2 || $arguments[0] !== '--filter')) {
        throw new RuntimeException('Optional arguments: --filter <test name>. Other PHPUnit overrides are intentionally refused.');
    }
    if (! extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('Enable PHP pdo_pgsql before running PostgreSQL tests.');
    }
    if (getenv('DOCKER_HOST') || getenv('DOCKER_CONTEXT')) {
        throw new RuntimeException('Use a local Docker context without DOCKER_HOST or DOCKER_CONTEXT overrides.');
    }
    $context = json_decode(dockerCommand(['context', 'inspect']), true, flags: JSON_THROW_ON_ERROR);
    $endpoint = $context[0]['Endpoints']['docker']['Host'] ?? '';
    if (! str_starts_with($endpoint, 'npipe://') && ! str_starts_with($endpoint, 'unix://')) {
        throw new RuntimeException('Only a local Docker socket is allowed.');
    }
    if (dockerCommand(['info', '--format', '{{.OSType}}']) !== 'linux'
        || version_compare(dockerCommand(['version', '--format', '{{.Server.Version}}']), '28.0.0', '<')) {
        throw new RuntimeException('A local Linux Docker engine version 28 or newer is required for localhost port isolation.');
    }
    $password = bin2hex(random_bytes(32));
    echo "Creating a disposable PostgreSQL 17 test server (first run may download the official image).\n";
    // Keep the owned name available for cleanup even if Docker times out creating it.
    $container = 'htg-scoring-tests-'.substr($runId, 0, 12);
    $createdId = dockerCommand([
        'create', '--name', $container,
        '--label', 'htg.scoring-test-run='.$runId,
        '--publish', '127.0.0.1::5432',
        '--tmpfs', '/var/lib/postgresql/data:rw,size=512m',
        '--env', 'POSTGRES_PASSWORD', '--env', 'POSTGRES_USER=htg_bootstrap', '--env', 'POSTGRES_DB=htg_scoring_test',
        'postgres:17-alpine', 'postgres', '-c', 'htg_test.run_id='.$runId,
    ], ['POSTGRES_PASSWORD' => $password], 300);
    if (! preg_match('/\A[a-f0-9]{64}\z/', $createdId)) {
        throw new RuntimeException('Docker did not return a valid container identity.');
    }
    $container = $createdId;
    dockerCommand(['start', $container]);
    $ready = false;
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $probe = new Process(['docker', 'exec', $container, 'pg_isready', '-h', '127.0.0.1', '-U', 'htg_bootstrap', '-d', 'htg_scoring_test']);
        $probe->setTimeout(5)->run();
        if ($probe->isSuccessful()) {
            $ready = true;
            break;
        }
        usleep(500000);
    }
    if (! $ready) {
        throw new RuntimeException('Disposable PostgreSQL did not become ready in time.');
    }
    // Migrations should work as the database owner without server-wide privileges.
    // Send the random password through stdin, never a command-line argument/file.
    dockerCommand(['exec', '-i', $container, 'psql', '-U', 'htg_bootstrap', '-d', 'htg_scoring_test', '-v', 'ON_ERROR_STOP=1'],
        input: "CREATE ROLE htg_test LOGIN PASSWORD '$password' NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION;\nALTER DATABASE htg_scoring_test OWNER TO htg_test;\n");
    $ports = json_decode(dockerCommand(['inspect', '--format', '{{json .NetworkSettings.Ports}}', $container]), true, flags: JSON_THROW_ON_ERROR);
    $binding = $ports['5432/tcp'] ?? [];
    if (count($binding) !== 1 || $binding[0]['HostIp'] !== '127.0.0.1') {
        throw new RuntimeException('Expected one localhost-only PostgreSQL port binding.');
    }
    $environment = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => '127.0.0.1',
        'DB_PORT' => $binding[0]['HostPort'], 'DB_DATABASE' => 'htg_scoring_test',
        'DB_USERNAME' => 'htg_test', 'DB_PASSWORD' => $password, 'DB_URL' => '',
        'HTG_TEST_POSTGRES_RUN_ID' => $runId,
        'PGOPTIONS' => false, 'PGSERVICE' => false, 'PGSERVICEFILE' => false, 'PGHOSTADDR' => false,
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    ];
    $tests = new Process([PHP_BINARY, '-d', 'memory_limit=512M', 'vendor/phpunit/phpunit/phpunit', '--configuration', 'phpunit.postgresql.xml', '--do-not-cache-result', ...$arguments], null, $environment);
    // The full integration suite now includes scorer screens and process races.
    // The expanded full suite exceeded 15 minutes on a verified local run.
    // Bound test resources explicitly, without changing application PHP settings.
    $tests->setTimeout(1800);
    $exitCode = $tests->run(function ($type, $output) use ($password): void {
        echo str_replace($password, '[redacted]', $output);
    });
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
} finally {
    $cleanup();
    if ($container !== null) {
        $exitCode = 1;
    }
}

exit($exitCode);
