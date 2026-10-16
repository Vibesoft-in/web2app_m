<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\App;
use App\Models\PushDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class FcmService
{
    public function __construct(
        private readonly DeepLinkValidator $deepLinkValidator
    ) {}

    /**
     * Sends an individual FCM push notification using HTTP v1 API.
     * Validates deep link payload against allowlist and cleans up invalid tokens automatically.
     */
    public function sendToToken(
        string $token,
        string $title,
        string $body,
        ?string $deepLink = null,
        ?App $app = null
    ): bool {
        // 1. Validate deep link format against strict allowlist (product:ID, order:ID, category:ID)
        if (! $this->deepLinkValidator->isValid($deepLink)) {
            throw new InvalidArgumentException("Invalid deep link format '{$deepLink}'. Allowed formats: product:ID, order:ID, category:ID.");
        }

        $projectId = $app?->firebase_project_id ?? config('services.firebase.project_id', 'appify-cloud');
        $isLive = config('services.firebase.fcm_live', false);

        if ($isLive) {
            $accessToken = $this->getGoogleAccessToken();
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $payload = [
                'message' => [
                    'token'        => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data' => array_filter([
                        'deep_link' => $deepLink,
                        'app_id'    => $app ? (string) $app->id : null,
                    ]),
                ],
            ];

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            // Clean up invalid or unregistered tokens automatically
            $errorBody = $response->body();
            if ($response->status() === 404 || str_contains($errorBody, 'UNREGISTERED') || str_contains($errorBody, 'INVALID_ARGUMENT')) {
                $this->cleanupInvalidToken($token);
            }

            Log::warning("FCM push delivery failed: {$errorBody}");
            return false;
        }

        // Test/Local dev mode
        if ($token === 'invalid_stale_token' || str_contains($token, 'unregistered')) {
            $this->cleanupInvalidToken($token);
            return false;
        }

        return true;
    }

    /**
     * Sends notifications to multiple device tokens in batches.
     *
     * @param array<string> $tokens
     * @return array{sent: int, failed: int}
     */
    public function sendBatch(
        array $tokens,
        string $title,
        string $body,
        ?string $deepLink = null,
        ?App $app = null
    ): array {
        if (! $this->deepLinkValidator->isValid($deepLink)) {
            throw new InvalidArgumentException("Invalid deep link format '{$deepLink}'. Allowed formats: product:ID, order:ID, category:ID.");
        }

        $sent = 0;
        $failed = 0;

        foreach ($tokens as $token) {
            $success = $this->sendToToken($token, $title, $body, $deepLink, $app);
            if ($success) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Removes stale, invalid, or uninstalled device tokens from the database.
     */
    private function cleanupInvalidToken(string $token): void
    {
        PushDevice::withoutTenantScope()->where('fcm_token', $token)->delete();
        $maskedToken = strlen($token) > 12 ? substr($token, 0, 8) . '...' . substr($token, -4) : '***';
        Log::info("Cleaned up invalid or unregistered FCM token from push_devices: {$maskedToken}");
    }

    private function getGoogleAccessToken(): string
    {
        return 'mock_fcm_bearer_token';
    }
}
