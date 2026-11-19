<?php

namespace Tests\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MatchSchedulingChecks;

class MatchSchedulingServiceTest extends PostgreSqlTestCase
{
    use MatchSchedulingChecks, RefreshDatabase;
}
