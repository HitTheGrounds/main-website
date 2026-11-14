<?php

namespace Tests\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TournamentGroupChecks;

class TournamentGroupServiceTest extends PostgreSqlTestCase
{
    use RefreshDatabase, TournamentGroupChecks;
}
