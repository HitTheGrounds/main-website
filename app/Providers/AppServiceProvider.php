<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Re-run only the original page's applicable checks on Livewire updates.
        // Guest login/registration pages do not acquire authenticated middleware.
        Livewire::addPersistentMiddleware([
            \App\Http\Middleware\EnsureTournamentEnabled::class,
            \App\Http\Middleware\EnsureCompanyAuthenticated::class,
            \App\Http\Middleware\EnsureCompanyAreaAccess::class,
            \App\Http\Middleware\EnsureUserIsAdmin::class,
            \App\Http\Middleware\EnsureUserIsScorer::class,
        ]);

        // Per-IP limits (the OTP/registration actions add their own tighter per-IP counters)
        RateLimiter::for('livewire', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('auth-pages', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Same /livewire/update URL, now throttled
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)->middleware(['web', 'throttle:livewire']));
    }
}
