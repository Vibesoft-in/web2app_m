<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'ulid'         => Str::ulid(),
            'name'         => fake()->company(),
            'owner_user_id' => null,
            'status'       => 'trial',
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}
