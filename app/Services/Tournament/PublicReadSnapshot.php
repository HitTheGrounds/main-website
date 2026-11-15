<?php

namespace App\Services\Tournament;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Short database-only public reads; no scorer/year write lock is acquired. */
class PublicReadSnapshot
{
    public function run(callable $read): mixed
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['pgsql', 'sqlite'], true) || $connection->transactionLevel() !== 0) {
            throw new LogicException('A public tournament snapshot requires its own PostgreSQL or SQLite transaction.');
        }
        $queryOnly = null;
        if ($driver === 'sqlite') {
            $queryOnly = (int) $connection->selectOne('PRAGMA query_only')->query_only;
            $connection->statement('PRAGMA query_only = ON');
        }
        try {
            return $connection->transaction(function () use ($connection, $driver, $read) {
                if ($driver === 'pgsql') {
                    $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                    $connection->statement("SET LOCAL statement_timeout = '3s'");
                    $connection->statement("SET LOCAL lock_timeout = '1s'");
                }

                return $read();
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['55P03', '57014', '40001'], true)) {
                abort(503, 'Tournament data is temporarily unavailable. Please refresh shortly.');
            }
            throw $exception;
        } finally {
            if ($queryOnly !== null) {
                $connection->statement('PRAGMA query_only = '.($queryOnly ? 'ON' : 'OFF'));
            }
        }
    }
}
