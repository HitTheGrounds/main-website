<?php

namespace Tests\PostgreSQL;

use Tests\Support\PostgreSqlTestDatabaseSafety;

class KnockoutScoringIntegrationTest extends \Tests\Feature\KnockoutScoringIntegrationTest
{
    protected function databaseSafetyClass(): string
    {
        return PostgreSqlTestDatabaseSafety::class;
    }
}
