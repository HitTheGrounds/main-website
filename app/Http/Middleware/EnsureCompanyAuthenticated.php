<?php

namespace App\Http\Middleware;

use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('company_token');

        if (!$token) {
            return redirect()->route('login');
        }

        $jwtService = new JWTService();
        $user = $jwtService->getUserFromToken($token);

        if (!$user) {
            // Token is invalid or expired, clear cookie
            cookie()->queue(cookie()->forget('company_token'));
            return redirect()->route('login');
        }

        // Attach user to request for easy access
        $request->attributes->set('user', $user);
        $request->attributes->set('company', $user); // Keep 'company' for backwards compatibility

        return $next($request);
    }
}
