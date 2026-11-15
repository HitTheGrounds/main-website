<?php

namespace Tests\PostgreSQL;

use Tests\Support\PostgreSqlTestDatabaseSafety;

// Run the exact same screen, adjustment and rollback checks on both engines.
class ScorerResultsScreenTest extends \Tests\Feature\ScorerResultsScreenTest
{
    protected function databaseSafetyClass(): string
    {
        return PostgreSqlTestDatabaseSafety::class;
    }
}
