<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new client account.
     * Creates a tenant + owner user in a single transaction.
     * Rate limited at the route level (5 attempts / 10 min per IP).
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        [$tenant, $user] = DB::transaction(function () use ($data) {
            // 1. Create tenant first (user needs tenant_id)
            $tenant = Tenant::create([
                'ulid'   => Str::ulid(),
                'name'   => $data['business_name'],
                'status' => 'trial',
            ]);

            // 2. Create owner user
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => $data['password'], // hashed via cast
                'role'      => 'owner',
            ]);

            // 3. Link owner back to tenant
            $tenant->update(['owner_user_id' => $user->id]);

            return [$tenant, $user];
        });

        // Send email verification
        event(new Registered($user));

        AuditLog::create([
            'tenant_id' => $tenant->id,
            'actor_id'  => $user->id,
            'action'    => 'auth.registered',
            'ip'        => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Registration successful. Please verify your email address.',
        ], 201);
    }

    /**
     * Authenticate and issue a Sanctum token.
     * Token is returned in the response body; caller should store in httpOnly cookie.
     * Rate limited at route level (10 attempts / 5 min per IP).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'error' => [
                    'code'    => 'EMAIL_NOT_VERIFIED',
                    'message' => 'Please verify your email address before logging in.',
                ],
            ], 403);
        }

        // Revoke previous tokens to enforce single-session (adjustable)
        $user->tokens()->delete();

        $token = $user->createToken('dashboard', ['*'], now()->addDays(30));

        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'actor_id'  => $user->id,
            'action'    => 'auth.login',
            'ip'        => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'token' => $token->plainTextToken,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    /**
     * Revoke the current token and log out.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            AuditLog::create([
                'tenant_id'  => $user->tenant_id,
                'actor_id'   => $user->id,
                'action'     => 'auth.logout',
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Send password reset link.
     * Rate limited: 3 requests per 15 min per email+IP to prevent abuse.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Always return 200 — don't reveal whether email exists (user enumeration)
        return response()->json([
            'message' => 'If that email is registered, you will receive a reset link shortly.',
        ]);
    }

    /**
     * Reset password using token from email link.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password]);
                $user->save();

                // Revoke ALL tokens on password reset (security)
                $user->tokens()->delete();

                AuditLog::create([
                    'tenant_id' => $user->tenant_id,
                    'actor_id'  => $user->id,
                    'action'    => 'auth.password_reset',
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_RESET_TOKEN',
                    'message' => 'This password reset link is invalid or has expired.',
                ],
            ], 422);
        }

        return response()->json(['message' => 'Password has been reset successfully.']);
    }
}
