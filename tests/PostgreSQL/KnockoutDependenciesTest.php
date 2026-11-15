<?php

namespace Tests\PostgreSQL;

use Tests\Support\PostgreSqlTestDatabaseSafety;

class KnockoutDependenciesTest extends \Tests\Feature\KnockoutDependenciesTest
{
    protected function databaseSafetyClass(): string
    {
        return PostgreSqlTestDatabaseSafety::class;
    }
}
