<?php

namespace App\Support;

use App\Helpers\CompanyAuth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ScorerWriteThrottle
{
    /** Call before validation or database/file changes in every scorer mutation. */
    public static function hit(): void
    {
        abort_unless(config('tournament.enabled') === true, 404);
        $user = CompanyAuth::user();
        abort_unless($user && $user->is_scorer, 403);

        // Identity comes from the authenticated account, never a form field.
        // All scorer actions share one allowance, independent of their IP.
        $key = 'tournament:scorer-write:'.$user->id;
        $maximum = max(1, (int) config('tournament.writes_per_minute', 30));

        if (RateLimiter::tooManyAttempts($key, $maximum)) {
            throw ValidationException::withMessages([
                'tournament' => 'Too many changes. Please try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }
}
