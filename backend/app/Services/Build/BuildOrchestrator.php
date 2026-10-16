<?php

declare(strict_types=1);

namespace App\Services\Build;

use App\Enums\BuildStatus;
use App\Jobs\RunBuildJob;
use App\Models\App;
use App\Models\Build;
use Illuminate\Validation\ValidationException;

class BuildOrchestrator
{
    private const MAX_CONCURRENT_BUILDS_PER_TENANT = 1;

    /**
     * Queues a new build with idempotency, concurrency limit, and config version pinning.
     */
    public function queueBuild(App $app): Build
    {
        // 1. Idempotency Check: if a build is already active, return the existing in-flight build
        $activeBuild = Build::withoutTenantScope()
            ->where('app_id', $app->id)
            ->whereIn('status', [
                BuildStatus::Queued,
                BuildStatus::Preparing,
                BuildStatus::Building,
                BuildStatus::Signing,
            ])
            ->first();

        if ($activeBuild) {
            return $activeBuild;
        }

        // 2. Validate prerequisites per docs §6: connection verified + config published
        if (! $app->isBuildAllowed()) {
            throw ValidationException::withMessages([
                'build' => ['Build not allowed: store connection must be verified and config published.'],
            ]);
        }

        // 3. Per-tenant concurrency check
        $tenantInFlight = Build::withoutTenantScope()
            ->where('tenant_id', $app->tenant_id)
            ->whereIn('status', [
                BuildStatus::Queued,
                BuildStatus::Preparing,
                BuildStatus::Building,
                BuildStatus::Signing,
            ])
            ->count();

        if ($tenantInFlight >= self::MAX_CONCURRENT_BUILDS_PER_TENANT) {
            throw ValidationException::withMessages([
                'concurrency' => ['Another build is currently in progress for your tenant account. Please wait for it to complete.'],
            ]);
        }

        // 4. Pin config version snapshot
        $publishedConfig = $app->configs()
            ->where('state', 'published')
            ->latest('version')
            ->first();

        $configVersion = $publishedConfig ? $publishedConfig->version : 1;

        // 5. Compute incremental version code
        $latestBuild = Build::withoutTenantScope()
            ->where('app_id', $app->id)
            ->latest('version_code')
            ->first();

        $nextVersionCode = $latestBuild && $latestBuild->version_code ? $latestBuild->version_code + 1 : 1;
        $versionName = '1.0.'.($nextVersionCode - 1);

        // 6. Create build record in Queued status
        $build = Build::withoutTenantScope()->create([
            'tenant_id'      => $app->tenant_id,
            'app_id'         => $app->id,
            'config_version' => $configVersion,
            'status'         => BuildStatus::Queued,
            'version_code'   => $nextVersionCode,
            'version_name'   => $versionName,
        ]);

        // 7. Dispatch queue job to worker
        RunBuildJob::dispatch($build->id);

        return $build;
    }
}
