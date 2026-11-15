<?php

namespace Tests\PostgreSQL;

use Tests\Support\PostgreSqlTestDatabaseSafety;

class TournamentSummaryRebuildTest extends \Tests\Feature\TournamentSummaryRebuildTest
{
    protected function databaseSafetyClass(): string
    {
        return PostgreSqlTestDatabaseSafety::class;
    }
}
