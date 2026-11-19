<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TournamentGroupChecks;
use Tests\TestCase;

class TournamentGroupServiceTest extends TestCase
{
    use RefreshDatabase, TournamentGroupChecks;
}
