<?php

namespace Tests\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TournamentSchemaChecks;

class TournamentSchemaTest extends PostgreSqlTestCase
{
    use RefreshDatabase, TournamentSchemaChecks;
}
