<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class IpThrottle
{
    /**
     * Count one attempt for the caller's IP and block it once $max is reached within $decaySeconds.
     * Complements the per-email/per-user limits, which an attacker can sidestep by rotating emails.
     */
    public static function hit(string $name, int $max, int $decaySeconds, string $field): void
    {
        $key = "ip:{$name}:" . request()->ip();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages([
                $field => 'Too many requests from your network. Please try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);
    }
}
