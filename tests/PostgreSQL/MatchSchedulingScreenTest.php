<?php

namespace Tests\PostgreSQL;

use Tests\Support\PostgreSqlTestDatabaseSafety;

class MatchSchedulingScreenTest extends \Tests\Feature\MatchSchedulingScreenTest
{
    protected function databaseSafetyClass(): string
    {
        return PostgreSqlTestDatabaseSafety::class;
    }
}
