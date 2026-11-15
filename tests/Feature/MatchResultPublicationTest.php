<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MatchResultPublicationChecks;
use Tests\TestCase;

class MatchResultPublicationTest extends TestCase
{
    use MatchResultPublicationChecks, RefreshDatabase;
}
