<?php

namespace App\Helpers;

use App\Models\User;
use App\Services\JWTService;
use Illuminate\Support\Facades\Route;

class CompanyAuth
{
    public static function dashboardRoute(User $user): string
    {
        if ($user->is_admin) {
            return 'admin.dashboard';
        }

        if ($user->is_scorer) {
            return config('tournament.enabled') === true && Route::has('scorer.dashboard')
                ? 'scorer.dashboard'
                : 'home';
        }

        // The existing admin dashboard middleware renders the approval screen
        // for organization-less accounts without granting them admin access.
        return $user->company_id !== null ? 'company.dashboard' : 'admin.dashboard';
    }

    public static function user(): ?User
    {
        // First try to get from request attributes (set by middleware)
        $user = request()->attributes->get('user');

        if ($user) {
            return $user;
        }

        // Fallback: get from JWT token in cookie
        $token = request()->cookie('company_token');

        if (! $token) {
            return null;
        }

        $jwtService = new JWTService;

        return $jwtService->getUserFromToken($token);
    }
}
