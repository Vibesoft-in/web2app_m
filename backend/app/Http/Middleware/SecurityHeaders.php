<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers to every response.
 * Belt-and-suspenders with Nginx headers for defense-in-depth.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-Request-Id', $request->header('X-Request-Id', (string) str()->uuid()));

        // Strict CSP for API responses (no HTML rendering expected)
        if ($request->expectsJson()) {
            $response->headers->set('Content-Security-Policy', "default-src 'none'");
        }

        // Remove leaky headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
