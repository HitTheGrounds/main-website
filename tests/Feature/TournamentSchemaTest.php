<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TournamentSchemaChecks;
use Tests\TestCase;

class TournamentSchemaTest extends TestCase
{
    use RefreshDatabase, TournamentSchemaChecks;
}
