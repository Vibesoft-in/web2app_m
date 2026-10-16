<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_creates_tenant_and_owner(): void
    {
        Event::fake([Registered::class]);

        $payload = [
            'name'                  => 'John Doe',
            'business_name'         => 'Doe Store Ltd',
            'email'                 => 'john@example.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Registration successful. Please verify your email address.',
            ]);

        // Tenant was created with trial status
        $this->assertDatabaseHas('tenants', [
            'name'   => 'Doe Store Ltd',
            'status' => 'trial',
        ]);

        $tenant = Tenant::where('name', 'Doe Store Ltd')->first();
        $this->assertNotNull($tenant);

        // User was created with role owner and correct tenant_id
        $this->assertDatabaseHas('users', [
            'email'     => 'john@example.com',
            'tenant_id' => $tenant->id,
            'role'      => 'owner',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($user->id, $tenant->owner_user_id);

        // Registered event dispatched
        Event::assertDispatched(Registered::class);

        // Audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id'  => $user->id,
            'action'    => 'auth.registered',
        ]);
    }

    public function test_registration_validation_rules(): void
    {
        // 1. Password under 10 chars rejected
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'business_name'         => 'Store',
            'email'                 => 'john@example.com',
            'password'              => 'short9!',
            'password_confirmation' => 'short9!',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 2. Duplicate email rejected
        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Another User',
            'business_name'         => 'Another Store',
            'email'                 => 'duplicate@example.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_when_email_is_not_verified(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'email'     => 'unverified@example.com',
            'password'  => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'unverified@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code'    => 'EMAIL_NOT_VERIFIED',
                    'message' => 'Please verify your email address before logging in.',
                ],
            ]);
    }

    public function test_login_succeeds_when_verified_and_issues_token(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id'         => $tenant->id,
            'email'             => 'verified@example.com',
            'password'          => Hash::make('Password123!'),
            'email_verified_at' => now(),
            'role'              => 'owner',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'verified@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ]);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id'  => $user->id,
            'action'    => 'auth.login',
        ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email'     => 'real@example.com',
            'password'  => Hash::make('CorrectPassword123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'real@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_logout_and_revokes_token(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id'         => $tenant->id,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('dashboard')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);

        // Token has been revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id'  => $user->id,
            'action'    => 'auth.logout',
        ]);
    }

    public function test_forgot_password_does_not_reveal_email_existence(): void
    {
        // Existing email
        $user = User::factory()->create(['email' => 'exists@example.com']);

        $res1 = $this->postJson('/api/auth/forgot-password', [
            'email' => 'exists@example.com',
        ]);
        $res1->assertStatus(200)
            ->assertJson(['message' => 'If that email is registered, you will receive a reset link shortly.']);

        // Non-existing email
        $res2 = $this->postJson('/api/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ]);
        $res2->assertStatus(200)
            ->assertJson(['message' => 'If that email is registered, you will receive a reset link shortly.']);
    }

    public function test_reset_password_with_valid_token_resets_password_and_revokes_tokens(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email'     => 'resetme@example.com',
            'password'  => Hash::make('OldPassword123!'),
        ]);

        $oldToken = $user->createToken('dashboard')->plainTextToken;
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $resetToken = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'token'                 => $resetToken,
            'email'                 => 'resetme@example.com',
            'password'              => 'NewPassword999!',
            'password_confirmation' => 'NewPassword999!',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password has been reset successfully.']);

        // Check password changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword999!', $user->password));

        // Tokens revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_id'  => $user->id,
            'action'    => 'auth.password_reset',
        ]);
    }
}
