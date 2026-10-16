<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Build\Firebase\FakeFirebaseProvisioner;
use App\Services\Build\Firebase\FirebaseManagementProvisioner;
use App\Services\Build\Firebase\FirebaseProvisionerInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FirebaseProvisionerInterface::class, function () {
            if (config('services.firebase.use_fake', true)) {
                return new FakeFirebaseProvisioner();
            }
            return new FirebaseManagementProvisioner(
                serviceAccountKeyPath: config('services.firebase.credentials_path'),
                defaultProjectId: config('services.firebase.project_id')
            );
        });
    }

    public function boot(): void
    {
    }
}
