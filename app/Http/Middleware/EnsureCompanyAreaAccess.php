<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAreaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('user');

        // Keep existing representative/admin behavior; dedicated scorers use their own area.
        abort_unless($user && ! $user->is_scorer, 403);

        return $next($request);
    }
}
