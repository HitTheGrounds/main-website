<?php

namespace Tests\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TournamentRosterChecks;

class TournamentRosterProtectionTest extends PostgreSqlTestCase
{
    use RefreshDatabase, TournamentRosterChecks;
}
