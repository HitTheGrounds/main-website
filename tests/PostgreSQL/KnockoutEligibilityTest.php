<?php

namespace Tests\PostgreSQL;

use Tests\Support\PostgreSqlTestDatabaseSafety;

class KnockoutEligibilityTest extends \Tests\Feature\KnockoutEligibilityTest
{
    protected function databaseSafetyClass(): string
    {
        return PostgreSqlTestDatabaseSafety::class;
    }
}
