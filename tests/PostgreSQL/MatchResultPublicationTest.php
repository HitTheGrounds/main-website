<?php

namespace Tests\PostgreSQL;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MatchResultPublicationChecks;

class MatchResultPublicationTest extends PostgreSqlTestCase
{
    use MatchResultPublicationChecks, RefreshDatabase;
}
