<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppStatus;
use App\Enums\Platform;
use App\Enums\SetupStep;
use App\Models\App;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<App>
 */
class AppFactory extends Factory
{
    protected $model = App::class;

    public function definition(): array
    {
        return [
            'ulid'         => Str::ulid(),
            'tenant_id'    => Tenant::factory(),
            'name'         => fake()->company() . ' App',
            'platform'     => Platform::WooCommerce,
            'package_name' => 'com.store.' . fake()->unique()->lexify('??????'),
            'app_key'      => 'app_' . Str::random(32),
            'setup_step'   => SetupStep::Platform,
            'status'       => AppStatus::Draft,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }

    public function shopify(): static
    {
        return $this->state(['platform' => Platform::Shopify]);
    }
}
