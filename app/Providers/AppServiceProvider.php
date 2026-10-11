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
        // Per-IP limits (the OTP/registration actions add their own tighter per-IP counters)
        RateLimiter::for('livewire', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('auth-pages', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Same /livewire/update URL, now throttled
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)->middleware(['web', 'throttle:livewire']));
    }
}
