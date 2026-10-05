<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Remove server fingerprinting headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        $isDev = app()->environment('local', 'testing') || file_exists(public_path('hot'));
        $viteOrigins = $isDev ? " http://127.0.0.1:5173 http://localhost:5173 ws://127.0.0.1:5173 ws://localhost:5173 http://[::1]:5173 ws://[::1]:5173" : "";
        $devEval = $isDev ? " 'unsafe-eval'" : "";

        // Content Security Policy — allows self + inline styles (DaisyUI) + trusted CDNs + Vite dev server
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'{$devEval} https://challenges.cloudflare.com https://unpkg.com{$viteOrigins}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com{$viteOrigins}",
            "font-src 'self' https://fonts.gstatic.com{$viteOrigins}",
            "img-src 'self' data: https:{$viteOrigins}",
            "connect-src 'self'{$viteOrigins}",
            "frame-src https://challenges.cloudflare.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
