<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the current tenant context from the authenticated user.
 *
 * Security contract: tenant_id is NEVER read from URL or request body.
 * It is always derived from the authenticated user's tenant_id.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() && $request->user() === null) {
            return response()->json([
                'error' => [
                    'code'    => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        // Tenant is already available via Auth::user()->tenant_id.
        // BelongsToTenant trait reads it from there automatically.
        // Nothing else needed here — this middleware is a checkpoint only.

        return $next($request);
    }
}
