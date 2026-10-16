<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ConnectionStatus;
use App\Enums\Platform;
use App\Enums\SetupStep;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetupStateController extends Controller
{
    /**
     * Get the setup wizard state for an app.
     *
     * GET /api/apps/{ulid}/setup-state
     */
    public function show(string $ulid): JsonResponse
    {
        $app = App::withoutTenantScope()->where('ulid', $ulid)->firstOrFail();

        $currentStep = $app->setup_step;

        // Once connection is verified, advance setup_step to theme so analysis, features, and theme are unlocked
        if ($app->connection && $app->connection->status === ConnectionStatus::Verified) {
            if ($currentStep->order() < SetupStep::Theme->order()) {
                $app->update(['setup_step' => SetupStep::Theme]);
                $currentStep = SetupStep::Theme;
            }
        }

        // If draft config exists with theme, unlock preview
        if ($app->configs()->where('state', 'draft')->exists()) {
            if ($currentStep->order() < SetupStep::Preview->order()) {
                $app->update(['setup_step' => SetupStep::Preview]);
                $currentStep = SetupStep::Preview;
            }
        }

        // If published config exists, unlock up to build
        if ($app->configs()->where('state', 'published')->exists()) {
            if ($currentStep->order() < SetupStep::Build->order()) {
                $app->update(['setup_step' => SetupStep::Build]);
                $currentStep = SetupStep::Build;
            }
        }

        $allSteps = SetupStep::cases();

        $completedSteps = [];
        $allowedSteps = [];

        foreach ($allSteps as $step) {
            if ($step->order() < $currentStep->order()) {
                $completedSteps[] = $step->value;
                $allowedSteps[] = $step->value;
            } elseif ($step->order() === $currentStep->order()) {
                $allowedSteps[] = $step->value;
            }
        }

        $activeSub = \App\Models\Subscription::withoutTenantScope()
            ->where('app_id', $app->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();
        $plan = $activeSub?->plan;
        $planName = $plan?->name ?? 'Starter';
        $canDownloadApk = true;
        $canDownloadAab = in_array($planName, ['Pro Growth', 'Enterprise']);
        $canDownloadIpa = in_array($planName, ['Pro Growth', 'Enterprise']);
        $hasAdminPanel = $planName === 'Enterprise';

        return response()->json([
            'app' => [
                'ulid'         => $app->ulid,
                'name'         => $app->name,
                'platform'     => $app->platform,
                'package_name' => $app->package_name,
                'status'       => $app->status,
            ],
            'current_step'    => $currentStep->value,
            'completed_steps' => $completedSteps,
            'allowed_steps'   => $allowedSteps,
            'build_allowed'   => $app->isBuildAllowed(),
            'build_blockers'  => $app->getBuildBlockers(),
            'subscription'    => [
                'has_active'         => $app->hasActiveSubscription(),
                'plan_id'            => $plan?->id ?? 1,
                'plan_name'          => $planName,
                'can_download_apk'   => $canDownloadApk,
                'can_download_aab'   => $canDownloadAab,
                'can_download_ipa'   => $canDownloadIpa,
                'admin_panel_access' => $hasAdminPanel,
                'formats'            => $hasAdminPanel ? ['APK', 'AAB', 'IPA', 'Admin Panel'] : ($canDownloadAab ? ['APK', 'AAB', 'IPA'] : ['APK']),
            ],
        ]);
    }

    /**
     * Change or select store platform.
     *
     * Invariant: Changing platform invalidates all later steps and resets the connection.
     *
     * POST /api/apps/{ulid}/platform
     */
    public function setPlatform(Request $request, string $ulid): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:woo,shopify'],
        ]);

        $newPlatform = Platform::from($validated['platform']);

        DB::transaction(function () use ($app, $newPlatform, $request) {
            $isPlatformChanged = $app->platform !== $newPlatform;

            if ($isPlatformChanged) {
                // Invalidate connection and subsequent step artifacts
                if ($app->connection) {
                    $app->connection->delete(); // delete connection credentials
                }

                // Invalidate unpublished draft configs
                $app->configs()->where('state', 'draft')->delete();

                // Roll back setup state to connect
                $app->update([
                    'platform'   => $newPlatform,
                    'setup_step' => SetupStep::Connect,
                ]);

                AuditLog::create([
                    'tenant_id'  => $app->tenant_id,
                    'actor_id'   => $request->user()?->id,
                    'action'     => 'app.platform_changed',
                    'target'     => "app:{$app->ulid}",
                    'ip'         => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'meta'       => ['new_platform' => $newPlatform->value],
                ]);
            } elseif ($app->setup_step === SetupStep::Platform) {
                $app->update([
                    'setup_step' => SetupStep::Connect,
                ]);
            }
        });

        $app->refresh();

        return response()->json([
            'message'      => 'Platform updated successfully.',
            'platform'     => $app->platform,
            'current_step' => $app->setup_step->value,
        ]);
    }

    /**
     * Roll back to an earlier wizard step.
     *
     * Invariant: Going back invalidates any dependent steps ahead of it.
     *
     * POST /api/apps/{ulid}/rollback
     */
    public function rollback(Request $request, string $ulid): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'target_step' => ['required', 'string', 'in:platform,connect,theme,branding'],
        ]);

        $target = SetupStep::from($validated['target_step']);

        if ($target->order() >= $app->setup_step->order()) {
            return response()->json([
                'message'      => 'No rollback needed (target step is not behind current step).',
                'current_step' => $app->setup_step->value,
            ]);
        }

        DB::transaction(function () use ($app, $target, $request) {
            // If rolling back to platform or connect, invalidate connection status
            if ($target === SetupStep::Platform || $target === SetupStep::Connect) {
                if ($app->connection) {
                    $app->connection->update([
                        'status' => ConnectionStatus::NotStarted,
                    ]);
                }
            }

            // Invalidate draft config if rolling back before theme
            if ($target->order() < SetupStep::Theme->order()) {
                $app->configs()->where('state', 'draft')->delete();
            }

            $app->update([
                'setup_step' => $target,
            ]);

            AuditLog::create([
                'tenant_id'  => $app->tenant_id,
                'actor_id'   => $request->user()?->id,
                'action'     => 'app.setup_rollback',
                'target'     => "app:{$app->ulid}",
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta'       => ['target_step' => $target->value],
            ]);
        });

        $app->refresh();

        return response()->json([
            'message'      => "Rolled back to step '{$target->value}'. Dependent steps have been invalidated.",
            'current_step' => $app->setup_step->value,
        ]);
    }
}
