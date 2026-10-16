<?php

declare(strict_types=1);

namespace App\Services\Build\Firebase;

class FirebaseProvisionResult
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $appId,
        public readonly string $googleServicesJson,
        public readonly bool $isMock = false
    ) {}
}
