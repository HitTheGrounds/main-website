<?php

namespace Tests;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseSafety;

abstract class TestCase extends BaseTestCase
{
    protected function databaseSafetyClass(): string
    {
        return TestDatabaseSafety::class;
    }

    public function createApplication()
    {
        $safety = $this->databaseSafetyClass();
        $safety::assertEnvironment();

        $app = require dirname(__DIR__).'/bootstrap/app.php';

        // Check after environment loading but before a cached PHP file is required.
        $app->beforeBootstrapping(LoadConfiguration::class, function ($app) use ($safety) {
            $safety::assertEnvironment();
            TestDatabaseSafety::assertConfigurationCacheIsAbsent($app->getCachedConfigPath());
        });

        // Run before providers boot or database-refresh traits can migrate anything.
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($safety) {
            $safety::assertConfiguration($app['config']->all());
            $connection = $safety::connectionName();

            // Do not leave a route to an application database via a secondary name.
            $app['config']->set('database.connections', [
                $connection => $app['config']->get('database.connections.'.$connection),
            ]);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUpTraits()
    {
        // Also catch config changed by test-case/parallel setup callbacks.
        $safety = $this->databaseSafetyClass();
        $safety::assertConfiguration($this->app['config']->all());

        if (array_keys($this->app['config']->get('database.connections', [])) !== [$safety::connectionName()]) {
            throw new \RuntimeException('Unsafe test setup: secondary database connections are not allowed before database-refresh traits.');
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Pages call @vite; tests do not need a compiled frontend
        $this->withoutVite();
    }

    /**
     * Send the following requests as this user, using the same JWT cookie the real login sets.
     */
    protected function signedIn(User $user): static
    {
        return $this->withCookie('company_token', (new JWTService)->generateToken($user));
    }

    /**
     * Make Livewire/Volt components see this user (they read it through CompanyAuth or the request attribute).
     */
    protected function actAs(User $user): void
    {
        request()->attributes->set('user', $user);
    }
}
