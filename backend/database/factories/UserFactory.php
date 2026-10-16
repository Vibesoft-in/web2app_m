<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'tenant_id'         => Tenant::factory(),
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => self::$password ??= Hash::make('Password123!'),
            'role'              => 'owner',
        ];
    }

    /** Create an unverified user. */
    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function editor(): static
    {
        return $this->state(['role' => 'editor']);
    }

    public function viewer(): static
    {
        return $this->state(['role' => 'viewer']);
    }

    /**
     * Create a user belonging to the given tenant.
     * Used in tests to attach multiple users to one tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(['tenant_id' => $tenant->id]);
    }
}
