<?php

declare(strict_types=1);

namespace App\Services\Build\Firebase;

use App\Models\App;

class FakeFirebaseProvisioner implements FirebaseProvisionerInterface
{
    public function registerAndroidApp(App $app, string $sha1, ?string $sha256 = null): FirebaseProvisionResult
    {
        $projectId = 'appify-'.substr($app->ulid, 0, 10);
        $appId = '1:109876543210:android:'.substr(md5($app->package_name), 0, 16);

        $googleServicesData = [
            'project_info' => [
                'project_number' => '109876543210',
                'project_id'     => $projectId,
                'storage_bucket' => "{$projectId}.appspot.com",
            ],
            'client' => [
                [
                    'client_info' => [
                        'mobilesdk_app_id' => $appId,
                        'android_client_info' => [
                            'package_name' => $app->package_name,
                        ],
                    ],
                    'oauth_client' => [
                        [
                            'client_id'   => "109876543210-mockoauthclient.apps.googleusercontent.com",
                            'client_type' => 1,
                            'android_info' => [
                                'package_name'     => $app->package_name,
                                'certificate_hash' => str_replace(':', '', strtolower($sha1)),
                            ],
                        ],
                    ],
                    'api_key' => [
                        [
                            'current_key' => 'AIzaSyMockKeyForDevTestingPurposes_'.substr($app->ulid, 0, 8),
                        ],
                    ],
                    'services' => [
                        'analytics_service' => ['status' => 1],
                        'appinvite_service' => ['status' => 1, 'other_platform_oauth_client' => []],
                        'ads_service'       => ['status' => 1],
                    ],
                ],
            ],
            'configuration_version' => '1',
        ];

        return new FirebaseProvisionResult(
            projectId: $projectId,
            appId: $appId,
            googleServicesJson: json_encode($googleServicesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            isMock: true
        );
    }

    public function addShaFingerprint(string $appId, string $shaFingerprint, string $certType = 'SHA_1'): void
    {
        // Fake implementation: no-op in testing
    }
}
