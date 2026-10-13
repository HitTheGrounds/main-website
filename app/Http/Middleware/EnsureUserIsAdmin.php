<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('user');

        if (!$user || !$user->is_admin) {
            if ($user && $user->company_id === null && $request->routeIs('admin.dashboard') && !$request->expectsJson()) {
                return response()->view('admin.awaiting-approval', ['user' => $user], 403);
            }

            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
