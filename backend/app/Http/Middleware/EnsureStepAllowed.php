<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SetupStep;
use App\Models\App;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the wizard setup state machine on the backend.
 *
 * Usage in routes: ->middleware('step:theme')
 *
 * Security: Prevents skipping ahead in the wizard via direct API / Postman calls.
 * Returns 403 STEP_LOCKED if the route's step requires progress beyond app's current setup_step.
 */
class EnsureStepAllowed
{
    public function handle(Request $request, Closure $next, string $requiredStep): Response
    {
        // Resolve app from route parameter (supports either {ulid} or {app} or model binding)
        $routeParam = $request->route('ulid') ?? $request->route('app');

        $app = null;
        if ($routeParam instanceof App) {
            $app = $routeParam;
        } elseif (is_string($routeParam)) {
            $app = App::where('ulid', $routeParam)->first();
        }

        if (! $app) {
            return response()->json([
                'error' => [
                    'code'    => 'APP_NOT_FOUND',
                    'message' => 'The requested app was not found.',
                ],
            ], 404);
        }

        $required = SetupStep::from($requiredStep);
        $current  = $app->setup_step;

        // Any attempt to skip ahead of the app's current step is blocked
        if ($required->order() > $current->order()) {
            return response()->json([
                'error' => [
                    'code'          => 'STEP_LOCKED',
                    'message'       => "Step '{$required->value}' is locked. Please complete '{$current->value}' first.",
                    'current_step'  => $current->value,
                    'required_step' => $required->value,
                ],
            ], 403);
        }

        return $next($request);
    }
}
