<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MatchStructureChecks;
use Tests\TestCase;

class MatchStructureValidationTest extends TestCase
{
    use MatchStructureChecks, RefreshDatabase;
}
