<?php

require dirname(__DIR__).'/vendor/autoload.php';

// Fail before any test/application setup, rather than trusting XML defaults.
Tests\Support\TestDatabaseSafety::assertEnvironment();
