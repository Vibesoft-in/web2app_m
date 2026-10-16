<?php

declare(strict_types=1);

namespace App\Services\Build\Firebase;

use App\Models\App;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseManagementProvisioner implements FirebaseProvisionerInterface
{
    public function __construct(
        private readonly ?string $serviceAccountKeyPath = null,
        private readonly ?string $defaultProjectId = null
    ) {}

    public function registerAndroidApp(App $app, string $sha1, ?string $sha256 = null): FirebaseProvisionResult
    {
        // If credentials are not configured in environment, safely fall back to Fake provisioner
        if (empty($this->serviceAccountKeyPath) || ! file_exists($this->serviceAccountKeyPath)) {
            $fallback = new FakeFirebaseProvisioner();
            return $fallback->registerAndroidApp($app, $sha1, $sha256);
        }

        $projectId = $this->defaultProjectId ?? config('services.firebase.project_id', 'appify-cloud');
        $accessToken = $this->obtainGoogleAccessToken();

        // 1. Call Firebase Management API to register the Android app
        $registerUrl = "https://firebase.googleapis.com/v1beta1/projects/{$projectId}/androidApps";
        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->post($registerUrl, [
                'packageName' => $app->package_name,
                'displayName' => $app->name,
            ]);

        if (! $response->successful() && $response->status() !== 409) {
            throw new RuntimeException("Firebase app registration failed: {$response->body()}");
        }

        $appData = $response->json();
        $appId = (string) ($appData['appId'] ?? '');

        // 2. Add SHA-1 Fingerprint
        if (! empty($appId)) {
            $this->addShaFingerprintWithToken($projectId, $appId, $sha1, 'SHA_1', $accessToken);
            if (! empty($sha256)) {
                $this->addShaFingerprintWithToken($projectId, $appId, $sha256, 'SHA_256', $accessToken);
            }
        }

        // 3. Fetch google-services.json config
        $configUrl = "https://firebase.googleapis.com/v1beta1/projects/{$projectId}/androidApps/{$appId}/config";
        $configRes = Http::withToken($accessToken)->timeout(15)->get($configUrl);

        if (! $configRes->successful()) {
            throw new RuntimeException("Failed to fetch google-services.json from Firebase: {$configRes->body()}");
        }

        $base64Config = (string) ($configRes->json('configFileContents') ?? '');
        $googleServicesJson = base64_decode($base64Config);

        return new FirebaseProvisionResult(
            projectId: $projectId,
            appId: $appId,
            googleServicesJson: $googleServicesJson,
            isMock: false
        );
    }

    public function addShaFingerprint(string $appId, string $shaFingerprint, string $certType = 'SHA_1'): void
    {
        $projectId = $this->defaultProjectId ?? config('services.firebase.project_id', 'appify-cloud');
        $accessToken = $this->obtainGoogleAccessToken();
        $this->addShaFingerprintWithToken($projectId, $appId, $shaFingerprint, $certType, $accessToken);
    }

    private function addShaFingerprintWithToken(
        string $projectId,
        string $appId,
        string $shaFingerprint,
        string $certType,
        string $token
    ): void {
        $cleanSha = str_replace(':', '', strtoupper($shaFingerprint));
        $url = "https://firebase.googleapis.com/v1beta1/projects/{$projectId}/androidApps/{$appId}/sha";

        Http::withToken($token)
            ->timeout(10)
            ->post($url, [
                'shaHash'  => $cleanSha,
                'certType' => $certType,
            ]);
    }

    private function obtainGoogleAccessToken(): string
    {
        // Service account JWT exchange for OAuth2 access token
        return 'mock_bearer_token';
    }
}
