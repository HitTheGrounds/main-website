<?php

namespace App\Http\Middleware;

use App\Helpers\CompanyAuth;
use App\Services\JWTService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyGuest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie('company_token');

        if ($token) {
            $jwtService = new JWTService;
            $user = $jwtService->getUserFromToken($token);

            if ($user) {
                return redirect()->route(CompanyAuth::dashboardRoute($user));
            }
        }

        return $next($request);
    }
}
