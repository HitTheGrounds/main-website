<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MatchSchedulingChecks;
use Tests\TestCase;

class MatchSchedulingServiceTest extends TestCase
{
    use MatchSchedulingChecks, RefreshDatabase;
}
