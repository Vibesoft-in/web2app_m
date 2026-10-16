<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BuildStatus;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\Build;
use App\Services\Build\BuildOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BuildController extends Controller
{
    public function __construct(
        private readonly BuildOrchestrator $orchestrator
    ) {}

    /**
     * Lists builds for an application.
     */
    public function index(string $ulid, Request $request): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        $builds = $app->builds()
            ->latest('id')
            ->get()
            ->map(fn (Build $b) => $this->transformBuild($b, $ulid));

        return response()->json(['data' => $builds]);
    }

    /**
     * Triggers/queues a new build via BuildOrchestrator.
     */
    public function store(string $ulid, Request $request): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();

        // Part B Attack 3: Build without payment is forbidden (403 PAYMENT_REQUIRED)
        if (! $app->hasActiveSubscription()) {
            return response()->json([
                'error' => [
                    'code'    => 'PAYMENT_REQUIRED',
                    'message' => 'Active subscription is required to generate builds.',
                ],
            ], 403);
        }

        $build = $this->orchestrator->queueBuild($app);

        return response()->json([
            'message' => 'Build job queued successfully.',
            'build'   => $this->transformBuild($build, $ulid),
        ], 201);
    }

    /**
     * Shows status of an individual build.
     */
    public function show(string $ulid, string $id, Request $request): JsonResponse
    {
        $app = App::where('ulid', $ulid)->firstOrFail();
        $build = $app->builds()->where('id', $id)->firstOrFail();

        return response()->json([
            'build' => $this->transformBuild($build, $ulid),
        ]);
    }

    /**
     * Returns an expiring signed URL for downloading the built artifact.
     * Enforces tenant authorization check.
     */
    public function download(string $ulid, string $id, Request $request): JsonResponse
    {
        // 1. Tenant ownership check (App belongsToTenant global scope)
        $app = App::where('ulid', $ulid)->firstOrFail();
        $build = $app->builds()->where('id', $id)->firstOrFail();

        // 2. Validate readiness
        if ($build->status !== BuildStatus::Ready || empty($build->artifact_path)) {
            return response()->json([
                'error' => [
                    'code'    => 'ARTIFACT_NOT_READY',
                    'message' => 'The build is not ready or has failed. Only completed builds can be downloaded.',
                ],
            ], 422);
        }

        $format = strtolower((string) $request->query('format', 'apk'));
        if (! in_array($format, ['apk', 'aab', 'ipa'])) {
            $format = 'apk';
        }

        // Validate plan capabilities: Starter = APK only; Pro & Enterprise = APK, AAB, IPA
        $activeSub = $app->subscriptions()->where('status', 'active')->latest('id')->first();
        $planName = $activeSub?->plan?->name ?? 'Starter';

        if (in_array($format, ['aab', 'ipa']) && $planName === 'Starter') {
            return response()->json([
                'error' => [
                    'code'    => 'UPGRADE_REQUIRED',
                    'message' => strtoupper($format) . ' format requires a Pro Growth or Enterprise plan. Starter tier includes APK only.',
                ],
            ], 403);
        }

        // 3. Generate 15-minute expiring signed download URL
        $expiresAt = now()->addMinutes(15);
        $signedUrl = URL::temporarySignedRoute(
            'api.apps.builds.download.file',
            $expiresAt,
            ['ulid' => $ulid, 'id' => $build->id, 'format' => $format]
        );

        return response()->json([
            'download_url' => $signedUrl,
            'format'       => $format,
            'expires_at'   => $expiresAt->toIso8601String(),
            'version_code' => $build->version_code,
            'version_name' => $build->version_name,
        ]);
    }

    /**
     * Serves the binary package via signed URL with format extension.
     */
    public function downloadFile(string $ulid, string $id, Request $request): BinaryFileResponse|JsonResponse
    {
        // Validates signature automatically when routed with 'signed' middleware
        $app = App::withoutTenantScope()->where('ulid', $ulid)->firstOrFail();
        $build = Build::withoutTenantScope()
            ->where('app_id', $app->id)
            ->where('id', $id)
            ->firstOrFail();

        $format = strtolower((string) $request->query('format', 'apk'));
        if (! in_array($format, ['apk', 'aab', 'ipa', 'admin'])) {
            $format = 'apk';
        }

        // Validate plan tier permissions
        $activeSub = \App\Models\Subscription::withoutTenantScope()
            ->where('app_id', $app->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();
        $planName = $activeSub?->plan?->name ?? 'Starter';

        if (in_array($format, ['aab', 'ipa']) && $planName === 'Starter') {
            return response()->json([
                'error' => strtoupper($format) . ' package requires a Pro Growth or Enterprise subscription.',
            ], 403);
        }

        if ($format === 'admin' && $planName !== 'Enterprise') {
            return response()->json([
                'error' => 'Dedicated Admin Panel access requires an Enterprise subscription.',
            ], 403);
        }

        $baseArtifact = $build->artifact_path ?: "tenants/{$app->tenant_id}/artifacts/apps/{$app->ulid}/app-release-v{$build->version_code}.aab";
        $dir = dirname($baseArtifact);
        $filenameWithoutExt = pathinfo($baseArtifact, PATHINFO_FILENAME);

        if ($format === 'admin') {
            $adminExportRel = "{$dir}/admin-panel-credentials.json";
            $adminPayload = json_encode([
                'app_name'           => $app->name,
                'app_ulid'           => $app->ulid,
                'package_name'       => $app->package_name,
                'tenant_id'          => $app->tenant_id,
                'plan_tier'          => 'Enterprise',
                'admin_portal_url'   => url('/admin'),
                'api_endpoint'       => url('/api'),
                'features_unlocked'  => ['Dedicated Admin Panel', 'FCM Push Notifications', 'Live Sync', 'VIP SLA'],
                'generated_at'       => now()->toIso8601String(),
                'instructions'       => 'Open the admin_portal_url in your browser to manage catalogs, notifications, and analytics.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            Storage::disk('local')->put($adminExportRel, $adminPayload);
            $fullPath = Storage::disk('local')->path($adminExportRel);
            $downloadFilename = Str::slug($app->name) . "-admin-portal-access.json";
            $contentType = 'application/json';
        } else {
            $formatSpecificPath = "{$dir}/{$filenameWithoutExt}.{$format}";
            $fullPath = Storage::disk('local')->path($formatSpecificPath);

            // Self-healing: if file missing, smaller than 1KB (mock/corrupt), or build not marked ready, compile real APK on the fly
            $isCorruptOrDummy = file_exists($fullPath) && filesize($fullPath) < 1024;
            if (! file_exists($fullPath) || $isCorruptOrDummy || $build->status !== BuildStatus::Ready) {
                $aabFullPath = Storage::disk('local')->path("{$dir}/{$filenameWithoutExt}.aab");
                $apkFullPath = Storage::disk('local')->path("{$dir}/{$filenameWithoutExt}.apk");
                $targetDir = dirname($apkFullPath);
                if (! is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                try {
                    \App\Services\Build\NativeApkGenerator::generate($app, $build, $apkFullPath, $aabFullPath);
                    $build->update([
                        'status'        => BuildStatus::Ready,
                        'artifact_path' => "{$dir}/{$filenameWithoutExt}.aab",
                        'error'         => null,
                    ]);
                    $fullPath = Storage::disk('local')->path($formatSpecificPath);
                } catch (\Throwable $e) {
                    if (! file_exists($fullPath) || filesize($fullPath) < 1024) {
                        return response()->json(['error' => 'Artifact generation error: ' . $e->getMessage()], 404);
                    }
                }
            }

            if (! file_exists($fullPath)) {
                return response()->json(['error' => 'Artifact file missing from storage.'], 404);
            }

            $downloadFilename = Str::slug($app->name) . "-v{$build->version_code}.{$format}";

            $contentType = match ($format) {
                'apk' => 'application/vnd.android.package-archive',
                'aab' => 'application/octet-stream',
                'ipa' => 'application/octet-stream',
                default => 'application/octet-stream',
            };
        }

        return response()->download($fullPath, $downloadFilename, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$downloadFilename}\"",
        ]);
    }

    private function transformBuild(Build $build, string $ulid): array
    {
        return [
            'id'             => $build->id,
            'status'         => $build->status->value,
            'config_version' => $build->config_version,
            'version_code'   => $build->version_code,
            'version_name'   => $build->version_name,
            'error'          => $build->error,
            'is_ready'       => $build->status === BuildStatus::Ready,
            'started_at'     => $build->started_at?->toIso8601String(),
            'finished_at'    => $build->finished_at?->toIso8601String(),
            'created_at'     => $build->created_at?->toIso8601String(),
        ];
    }
}
