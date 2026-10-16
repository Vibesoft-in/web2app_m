<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Build;
use App\Enums\BuildStatus;
use App\Services\Build\NativeApkGenerator;
use Illuminate\Support\Facades\Storage;

echo "Finding ready builds...\n";
$readyBuilds = Build::withoutTenantScope()->where('status', BuildStatus::Ready)->get();

foreach ($readyBuilds as $b) {
    $a = $b->app;
    if (!$a) {
        continue;
    }

    echo "Building real signed APK for: {$a->name} (App ID: {$a->id}, Build ID: {$b->id})\n";
    $baseArtifact = $b->artifact_path ?: "tenants/{$a->tenant_id}/artifacts/apps/{$a->ulid}/app-release-v{$b->version_code}.aab";
    
    $dir = dirname($baseArtifact);
    $filenameWithoutExt = pathinfo($baseArtifact, PATHINFO_FILENAME);
    
    $apkRelative = "{$dir}/{$filenameWithoutExt}.apk";
    $aabRelative = "{$dir}/{$filenameWithoutExt}.aab";
    
    $apkPath = Storage::disk('local')->path($apkRelative);
    $aabPath = Storage::disk('local')->path($aabRelative);

    $logs = NativeApkGenerator::generate($a, $b, $apkPath, $aabPath);
    echo "Success! Real signed APK generated at: {$apkPath} (" . filesize($apkPath) . " bytes)\n";
    foreach ($logs as $l) {
        echo "  " . $l . "\n";
    }

    // Ensure build points to valid artifact_path
    $b->update(['artifact_path' => $aabRelative]);
}

echo "All ready builds upgraded to real installable Android APKs!\n";
