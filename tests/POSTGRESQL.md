# Disposable PostgreSQL tests

Normal development uses the existing SQLite database. `composer test` still runs
only SQLite memory tests. No application database setting needs changing.

## Requirements and commands

- PHP with `pdo_pgsql`, Composer dependencies installed, and Docker CLI available.
- A running local Linux Docker engine version 28 or newer (Docker Desktop works).
- Internet access for the first download of the official `postgres:17-alpine` image.
- An uncached checkout. Cached Laravel configuration is refused, never deleted.
- Do not set `DOCKER_HOST` or `DOCKER_CONTEXT`; select your local context normally.

From the repository root:

```powershell
composer test:postgresql
composer test:postgresql -- --filter PostgreSqlIsolationTest
```

The runner allows an optional `--filter <test name>` only. It refuses configuration,
bootstrap, and other PHPUnit overrides. Run the normal SQLite suite separately:

```powershell
composer test -- --do-not-cache-result
```

Do not point PHPUnit at the development or department database. Running
`phpunit.postgresql.xml` directly without the runner's environment is refused.
Do not put PostgreSQL test credentials in `.env`, XML, or source control.

## What the runner does

1. Checks PHP and the local Docker engine without booting Laravel.
2. Creates its own labelled container with a random password and run marker.
3. Publishes PostgreSQL on an automatically chosen port bound to `127.0.0.1` only.
4. Uses temporary container memory storage; no host folder or existing volume is mounted.
5. Creates a separate test user with database ownership and no server administration privileges.
6. Starts the separate PostgreSQL PHPUnit suite with child-process settings only.
7. Removes exactly its own container and volumes on normal completion or test failure.

The guard checks the environment, resolved connection, service settings, and a
random server startup marker before providers or migration traits run. Database
name alone is insufficient. Connections are restricted to that one test target.
The base class checks again immediately before migration traits. Cache, sessions,
and mail stay in memory; queues execute synchronously.

The credentials exist in process/container memory and Docker container metadata
during the run; they are never written to repository files or printed by the runner.
Temporary memory storage can be swapped by the operating system. Use synthetic data
only; never load private production dumps here.

## Adding database-specific tests

Put them under `tests/PostgreSQL/` and extend `PostgreSqlTestCase`. Use
`RefreshDatabase` as needed. For independent sessions, use
`PostgreSqlTestDatabaseSafety::connect(config('database.connections.pgsql'))`.
Always roll back transactions and release resources in `finally` blocks.
Separate sessions cannot see uncommitted fixtures inside `RefreshDatabase`'s
transaction; later publication/concurrency tests must arrange committed fixtures
deliberately inside this disposable database.

Initial checks cover fresh existing migrations, JSONB, independent backend sessions,
and transaction lock contention/release. They do not yet test tournament features,
match publication races, existing-data preservation, or the department deployment.
PostgreSQL 17 is the local test baseline; the department server's actual major
version remains unverified and must be matched before release.

## Interrupted runs

Normal failures clean up automatically. A forced process kill, Docker shutdown,
or machine crash can prevent cleanup. List only this runner's containers:

```powershell
docker ps -a --filter label=htg.scoring-test-run --format "{{.ID}} {{.Names}}"
```

After checking which run was interrupted, remove its exact displayed ID:

```powershell
docker rm --force --volumes <that-container-id>
```

Do not use global prune, remove other containers, or delete existing volumes.
This setup never migrates the existing SQLite file or the department database.

Docker behavior follows its documentation for [localhost port publishing](https://docs.docker.com/engine/network/port-publishing/)
and [temporary memory mounts](https://docs.docker.com/engine/storage/tmpfs/).
The server marker uses PostgreSQL's [custom settings](https://www.postgresql.org/docs/17/runtime-config-custom.html).
