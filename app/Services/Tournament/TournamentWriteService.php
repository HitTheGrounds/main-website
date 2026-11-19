<?php

namespace App\Services\Tournament;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

class TournamentWriteService
{
    // Stable two-integer PostgreSQL key: HTG namespace, then tournament year.
    public const LOCK_NAMESPACE = 0x485447;

    /**
     * Authorize/throttle before calling; recheck permissions/versions in $write.
     * Acquire this lock before row locks. Within each table, lock IDs ascending:
     * groups, matches, teams, group_teams, team_members, player_match_stats, player_tournament_stats.
     * Use RosterProtectionService for player membership locks (team before player).
     * Keep callbacks database-only and short; never send mail or make network calls.
     */
    public function run(int $year, callable $write): mixed
    {
        if ($year < 1 || $year > 9999) {
            throw new InvalidArgumentException('A valid tournament year is required.');
        }

        $connection = DB::connection();
        if (! in_array($connection->getDriverName(), ['pgsql', 'sqlite'], true)) {
            throw new LogicException('Tournament writes require PostgreSQL or development SQLite.');
        }

        try {
            // One attempt: never replay a callback that might have external effects.
            return $connection->transaction(function () use ($connection, $year, $write) {
                $previousTimeout = null;
                if ($connection->getDriverName() === 'pgsql') {
                    $previousTimeout = $this->lockYear($connection, $year);
                }

                $result = $write();
                // Public pages poll this revision (tournament.rev route) to know when to refresh.
                DB::afterCommit(fn () => self::bumpRevision($year));
                if ($previousTimeout !== null) {
                    $connection->selectOne("SELECT set_config('lock_timeout', ?, true)", [$previousTimeout]);
                }

                return $result;
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['55P03', '40P01', '40001'], true)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'tournament' => 'Another update is in progress. Please reload and try again.',
            ]);
        }
    }

    public static function revisionKey(int $year): string
    {
        return "tournament.rev.$year";
    }

    private static function bumpRevision(int $year): void
    {
        $key = self::revisionKey($year);
        if (! Cache::has($key)) {
            Cache::forever($key, 0);
        }
        Cache::increment($key);
    }

    private function lockYear(Connection $connection, int $year): string
    {
        if ($connection->transactionLevel() === 1) {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        } elseif ($connection->selectOne('SHOW transaction_isolation')->transaction_isolation !== 'read committed') {
            // A savepoint cannot safely change the caller's snapshot/isolation.
            throw new LogicException('Nested tournament writes require a read-committed transaction.');
        }

        $previousTimeout = $connection->selectOne('SHOW lock_timeout')->lock_timeout;
        $connection->selectOne("SELECT set_config('lock_timeout', '3s', true)");
        $connection->selectOne('SELECT pg_advisory_xact_lock(?, ?)', [self::LOCK_NAMESPACE, $year]);

        // The lock lasts until the outermost commit/rollback. Callback row locks
        // use the same bounded wait; run() restores the timeout after success.
        return $previousTimeout;
    }
}
