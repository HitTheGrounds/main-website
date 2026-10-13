<?php

namespace Tests;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
