<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-limits by tenant + IP, not just IP alone.
 * This prevents one noisy tenant from impacting others.
 *
 * Usage: ->middleware('throttle.tenant:60,1') (60 requests per 1 minute)
 */
class ThrottleByTenant
{
    public function __construct(
        protected RateLimiter $limiter,
    ) {}

    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $tenantId = Auth::user()?->tenant_id ?? 'guest'; // @phpstan-ignore-line
        $ip       = $request->ip();
        $key      = "t:{$tenantId}:ip:{$ip}";

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            return response()->json([
                'error' => [
                    'code'        => 'RATE_LIMIT_EXCEEDED',
                    'message'     => 'Too many requests. Please slow down.',
                    'retry_after' => $retryAfter,
                ],
            ], 429)->withHeaders([
                'Retry-After'           => $retryAfter,
                'X-RateLimit-Limit'     => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set(
            'X-RateLimit-Remaining',
            (string) $this->limiter->remaining($key, $maxAttempts)
        );

        return $response;
    }
}
