<?php

declare(strict_types=1);

namespace App\Services\Build\Firebase;

use App\Models\App;

interface FirebaseProvisionerInterface
{
    /**
     * Registers an Android application in Firebase, attaches SHA fingerprints,
     * and downloads the google-services.json configuration payload.
     */
    public function registerAndroidApp(App $app, string $sha1, ?string $sha256 = null): FirebaseProvisionResult;

    /**
     * Adds an SHA-1 or SHA-256 certificate fingerprint to an existing Firebase Android app.
     */
    public function addShaFingerprint(string $appId, string $shaFingerprint, string $certType = 'SHA_1'): void;
}
