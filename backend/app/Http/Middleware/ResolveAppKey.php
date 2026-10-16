<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\App;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current app and tenant from the X-App-Key header.
 * Used for the mobile API (/v1) routes.
 *
 * Security: tenant_id is derived from the app record, never from request input.
 */
class ResolveAppKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-App-Key');

        if (empty($key)) {
            return response()->json([
                'error' => [
                    'code'    => 'MISSING_APP_KEY',
                    'message' => 'X-App-Key header is required.',
                ],
            ], 401);
        }

        // Cache the app lookup to avoid a DB hit on every request.
        $app = Cache::remember(
            "app_key:{$key}",
            now()->addMinutes(5),
            fn () => App::withoutTenantScope()->where('app_key', $key)->first()
        );

        if (! $app) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_APP_KEY',
                    'message' => 'Invalid or inactive app key.',
                ],
            ], 401);
        }

        // Throttle by resolved app and client IP (120 req/minute)
        $throttleKey = "v1_throttle:{$app->id}:" . $request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 120)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
            return response()->json([
                'error' => [
                    'code'    => 'RATE_LIMIT_EXCEEDED',
                    'message' => "Too many requests for this app. Please retry in {$seconds} seconds.",
                ],
            ], 429);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60);

        // Bind to request for use in controllers and other middleware.
        $request->attributes->set('resolved_app', $app);
        $request->attributes->set('resolved_tenant_id', $app->tenant_id);

        return $next($request);
    }
}
