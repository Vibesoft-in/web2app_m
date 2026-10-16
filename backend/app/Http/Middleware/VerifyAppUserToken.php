<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\App;
use App\Models\AppUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates that the bearer token is scoped specifically to the current app (from X-App-Key).
 * Prevents cross-app token reuse (Token for App X used against App Y).
 */
class VerifyAppUserToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof AppUser) {
            return response()->json([
                'error' => [
                    'code'    => 'UNAUTHORIZED',
                    'message' => 'Authentication required for this endpoint.',
                ],
            ], 401);
        }

        /** @var App|null $app */
        $app = $request->attributes->get('resolved_app');

        if (! $app) {
            return response()->json([
                'error' => [
                    'code'    => 'MISSING_APP_CONTEXT',
                    'message' => 'App context could not be resolved.',
                ],
            ], 400);
        }

        // 1. Verify app_id on the user record matches the resolved app
        if ((int) $user->app_id !== (int) $app->id) {
            return response()->json([
                'error' => [
                    'code'    => 'FORBIDDEN_APP_MISMATCH',
                    'message' => 'This token is not valid for the requested application.',
                ],
            ], 403);
        }

        // 2. Verify token abilities are scoped to app:{id}
        $expectedAbility = "app:{$app->id}";
        if (! $user->tokenCan($expectedAbility)) {
            return response()->json([
                'error' => [
                    'code'    => 'FORBIDDEN_APP_MISMATCH',
                    'message' => 'Token ability is not scoped to this application.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
