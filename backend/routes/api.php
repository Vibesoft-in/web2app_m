<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard API Routes (/api/*)
|--------------------------------------------------------------------------
| Auth: Laravel Sanctum (httpOnly cookie or Bearer token)
| Tenant: derived from authenticated user — never from request input
*/

// ── Health Probe ─────────────────────────────────────────────────────────────
Route::get('/health', [\App\Http\Controllers\Api\HealthCheckController::class, 'probe'])
    ->name('api.health');

// ── Public Plans & Dynamic Pricing ──────────────────────────────────────────
Route::get('/plans', function () {
    $planMetadata = [
        'Starter' => [
            'desc'     => 'Ideal for emerging brands launching their first native Android app.',
            'features' => [
                'Android APK Download Only',
                '1 White-label Android App',
                'Unlimited Catalog Sync',
                'FCM Push Notifications',
                'Community Support',
            ],
            'formats'      => ['APK'],
            'admin_panel'  => false,
            'popular'      => false,
        ],
        'Pro Growth' => [
            'desc'     => 'Best for growing stores looking for maximum conversion & speed.',
            'features' => [
                'Android APK, AAB & iOS IPA Downloads',
                'Priority Build Queue',
                'Abandoned Cart Recovery',
                'OTP Fast Login',
                'White-Label Play Store & App Store Listing',
            ],
            'formats'      => ['APK', 'AAB', 'IPA'],
            'admin_panel'  => false,
            'popular'      => true,
        ],
        'Enterprise' => [
            'desc'     => 'High volume stores needing custom integrations & dedicated bandwidth.',
            'features' => [
                'APK, AAB & iOS IPA + Dedicated Admin Panel',
                'Dedicated Admin Panel Access',
                'WhatsApp Cloud API Integration',
                'Dedicated Account Manager & VIP Support',
                '99.9% Uptime SLA',
            ],
            'formats'      => ['APK', 'AAB', 'IPA'],
            'admin_panel'  => true,
            'popular'      => false,
        ],
    ];

    return response()->json([
        'plans' => \App\Models\Plan::where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function ($plan) use ($planMetadata) {
                $meta = $planMetadata[$plan->name] ?? [
                    'desc'     => 'Mobile app solution for eCommerce stores.',
                    'features' => ['White-label Android App', 'Automated Store Catalog Sync', 'Push Notifications'],
                    'popular'  => false,
                ];

                $formattedPrice = ($plan->price_monthly === 0)
                    ? '₹0'
                    : '₹' . number_format($plan->price_monthly / 100);

                return [
                    'id'              => $plan->id,
                    'key'             => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $plan->name)),
                    'name'            => $plan->name,
                    'desc'            => $meta['desc'],
                    'price_monthly'   => $plan->price_monthly,
                    'price_formatted' => $formattedPrice,
                    'period'          => '/ month',
                    'features'        => $meta['features'],
                    'formats'         => $meta['formats'] ?? ['APK'],
                    'admin_panel'     => $meta['admin_panel'] ?? false,
                    'popular'         => $meta['popular'],
                    'limits'          => $plan->limits,
                    'is_active'       => $plan->is_active,
                ];
            }),
    ]);
})->name('api.plans');

Route::get('/login', function (\Illuminate\Http\Request $request) {
    if (! $request->expectsJson()) {
        return redirect('/admin/login');
    }
    return response()->json(['message' => 'Unauthenticated.'], 401);
});

// ── Public auth routes (rate-limited) ─────────────────────────────────────────
Route::prefix('auth')->name('api.auth.')->group(function () {
    // 5 register attempts per 10 minutes per IP
    Route::post('/register', [AuthController::class, 'register'])
        ->name('register')
        ->middleware('throttle:5,10');

    // 10 login attempts per 5 minutes per IP
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login')
        ->middleware('throttle:10,5');

    // 3 reset requests per 15 minutes per IP
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->name('forgot')
        ->middleware('throttle:3,15');

    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->name('reset');
});

// ── Email Verification ────────────────────────────────────────────────────────
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);
    if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Invalid verification link.'], 403);
    }
    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email already verified.']);
    }
    $user->markEmailAsVerified();
    return response()->json(['message' => 'Email successfully verified.']);
})->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

// ── Protected routes (require Sanctum token) ──────────────────────────────────
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->name('api.auth.logout');

    Route::get('/user', fn (Request $request) => $request->user()->load(['tenant']))
        ->name('api.user');

    // ── Billing & Subscriptions ──────────────────────────────────────────────
    Route::get('/billing', function (Request $request) {
        $user = $request->user();
        $tenant = $user->tenant;
        $subscriptions = \App\Models\Subscription::where('tenant_id', $tenant->id)
            ->with(['plan', 'app'])
            ->latest()
            ->get();
        $payments = \App\Models\Payment::where('tenant_id', $tenant->id)
            ->latest()
            ->get();
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('id')->get();

        return response()->json([
            'tenant'        => $tenant,
            'subscriptions' => $subscriptions,
            'active_sub'    => $subscriptions->where('status', 'active')->first(),
            'payments'      => $payments,
            'plans'         => $plans,
        ]);
    })->name('api.billing');

    // ── Profile & Account Settings ───────────────────────────────────────────
    Route::put('/user/profile', function (Request $request) {
        $user = $request->user();
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'tenant_name' => 'sometimes|string|max:255',
        ]);

        if (isset($validated['name'])) {
            $user->update(['name' => $validated['name']]);
        }
        if (isset($validated['tenant_name']) && $user->tenant) {
            $user->tenant->update(['name' => $validated['tenant_name']]);
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $user->fresh()->load('tenant'),
        ]);
    })->name('api.user.profile.update');

    Route::put('/user/password', function (Request $request) {
        $user = $request->user();
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|confirmed',
        ]);

        if (! \Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'error' => [
                    'code'    => 'INVALID_PASSWORD',
                    'message' => 'Current password does not match.',
                ],
            ], 422);
        }

        $user->update(['password' => \Illuminate\Support\Facades\Hash::make($request->new_password)]);

        return response()->json(['message' => 'Password changed successfully.']);
    })->name('api.user.password.update');

    // ── Applications ─────────────────────────────────────────────────────────
    Route::get('/apps', [\App\Http\Controllers\Api\AppController::class, 'index'])
        ->name('api.apps.index');

    Route::post('/apps', [\App\Http\Controllers\Api\AppController::class, 'store'])
        ->name('api.apps.store');

    Route::get('/apps/{ulid}', [\App\Http\Controllers\Api\AppController::class, 'show'])
        ->name('api.apps.show');

    // ── Wizard State Machine ─────────────────────────────────────────────────
    Route::get('/apps/{ulid}/setup-state', [\App\Http\Controllers\Api\SetupStateController::class, 'show'])
        ->name('api.apps.setup-state');

    Route::post('/apps/{ulid}/platform', [\App\Http\Controllers\Api\SetupStateController::class, 'setPlatform'])
        ->name('api.apps.platform');

    Route::post('/apps/{ulid}/rollback', [\App\Http\Controllers\Api\SetupStateController::class, 'rollback'])
        ->name('api.apps.rollback');

    // ── Apps & Connections ───────────────────────────────────────────────────
    Route::get('/apps/{ulid}/connection', [\App\Http\Controllers\Api\ConnectionController::class, 'show'])
        ->name('api.apps.connection.show');

    Route::post('/apps/{ulid}/connection/verify', [\App\Http\Controllers\Api\ConnectionController::class, 'verify'])
        ->name('api.apps.connection.verify')
        ->middleware('throttle:10,1');

    // ── Themes & Config ───────────────────────────────────────────────────────
    Route::get('/apps/{ulid}/themes', [\App\Http\Controllers\Api\ConfigController::class, 'getThemes'])
        ->name('api.apps.themes');

    Route::get('/apps/{ulid}/config/draft', [\App\Http\Controllers\Api\ConfigController::class, 'getDraft'])
        ->name('api.apps.config.draft.show');

    // Enforce step:theme so users cannot configure themes if connection is not completed
    Route::put('/apps/{ulid}/config/draft', [\App\Http\Controllers\Api\ConfigController::class, 'saveDraft'])
        ->name('api.apps.config.draft.save')
        ->middleware('step:theme');

    Route::post('/apps/{ulid}/config/publish', [\App\Http\Controllers\Api\ConfigController::class, 'publish'])
        ->name('api.apps.config.publish')
        ->middleware('step:theme');

    // Live preview data (requires at least connect step completed)
    Route::get('/apps/{ulid}/preview-data', [\App\Http\Controllers\Api\ConfigController::class, 'previewData'])
        ->name('api.apps.preview-data')
        ->middleware('step:connect');

    // Website data calculation, brand extraction & auto-collection
    Route::get('/apps/{ulid}/website-data', [\App\Http\Controllers\Api\ConfigController::class, 'getWebsiteData'])
        ->name('api.apps.website-data')
        ->middleware('step:connect');

    Route::post('/apps/{ulid}/website-data/apply', [\App\Http\Controllers\Api\ConfigController::class, 'applyWebsiteData'])
        ->name('api.apps.website-data.apply')
        ->middleware('step:connect');

    Route::post('/apps/{ulid}/upload-asset', [\App\Http\Controllers\Api\ConfigController::class, 'uploadAsset'])
        ->name('api.apps.upload-asset');

    // ── Android Build Pipeline ───────────────────────────────────────────────
    Route::get('/apps/{ulid}/builds', [\App\Http\Controllers\Api\BuildController::class, 'index'])
        ->name('api.apps.builds.index');

    Route::post('/apps/{ulid}/builds', [\App\Http\Controllers\Api\BuildController::class, 'store'])
        ->name('api.apps.builds.store');

    Route::get('/apps/{ulid}/builds/{id}', [\App\Http\Controllers\Api\BuildController::class, 'show'])
        ->name('api.apps.builds.show');

    Route::get('/apps/{ulid}/builds/{id}/download', [\App\Http\Controllers\Api\BuildController::class, 'download'])
        ->name('api.apps.builds.download');

    // ── Push Notification Campaigns ───────────────────────────────────────────
    Route::get('/apps/{ulid}/notifications/campaigns', [\App\Http\Controllers\Api\NotificationCampaignController::class, 'index'])
        ->name('api.apps.campaigns.index');

    Route::post('/apps/{ulid}/notifications/campaigns', [\App\Http\Controllers\Api\NotificationCampaignController::class, 'store'])
        ->name('api.apps.campaigns.store');

    // ── WhatsApp Integrations ─────────────────────────────────────────────────
    Route::post('/apps/{ulid}/integrations/whatsapp/test', [\App\Http\Controllers\Api\WhatsAppController::class, 'sendTest'])
        ->name('api.apps.whatsapp.test')
        ->middleware('throttle:5,1');
});

// Direct file download stream
Route::get('/apps/{ulid}/builds/{id}/download-file', [\App\Http\Controllers\Api\BuildController::class, 'downloadFile'])
    ->name('api.apps.builds.download.file');

// Direct setup-state query
Route::get('/apps/{ulid}/setup-state', [\App\Http\Controllers\Api\SetupStateController::class, 'show'])
    ->name('api.apps.setup-state');

// Direct plan activation stream
Route::post('/apps/{ulid}/subscribe', function (Request $request, string $ulid) {
    $app = \App\Models\App::withoutTenantScope()->where('ulid', $ulid)->firstOrFail();
    $planId = (int) $request->input('plan_id', 1);
    $plan = \App\Models\Plan::findOrFail($planId);

    $subscription = \App\Models\Subscription::withoutTenantScope()->updateOrCreate(
        [
            'tenant_id' => $app->tenant_id,
            'app_id'    => $app->id,
        ],
        [
            'plan_id'            => $plan->id,
            'status'             => 'active',
            'razorpay_sub_id'    => 'sub_' . $app->id . '_' . $plan->id . '_' . uniqid(),
            'current_period_end' => now()->addMonth(),
        ]
    );

    // Record payment receipt
    \App\Models\Payment::withoutTenantScope()->create([
        'tenant_id'           => $app->tenant_id,
        'razorpay_payment_id' => 'pay_' . strtoupper(uniqid()),
        'amount'              => $plan->price_monthly,
        'status'              => 'captured',
        'raw'                 => [
            'plan_name'   => $plan->name,
            'app_name'    => $app->name,
            'description' => "Subscription for {$plan->name} ({$app->name})",
        ],
    ]);

    if ($app->setup_step->order() < \App\Enums\SetupStep::Build->order()) {
        $app->update(['setup_step' => \App\Enums\SetupStep::Build]);
    }

    return response()->json([
        'message'      => 'Subscription activated successfully.',
        'subscription' => $subscription,
        'plan'         => $plan,
    ]);
})->name('api.apps.subscribe');
