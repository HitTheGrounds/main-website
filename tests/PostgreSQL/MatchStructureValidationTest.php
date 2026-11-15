<?php

namespace Tests\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MatchStructureChecks;

class MatchStructureValidationTest extends PostgreSqlTestCase
{
    use MatchStructureChecks, RefreshDatabase;
}
