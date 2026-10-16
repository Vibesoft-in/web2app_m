<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConnectionStatus;
use App\Enums\Platform;
use App\Enums\SetupStep;
use App\Models\App;
use App\Models\Connection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private App $appA;
    private App $appB;
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = User::factory()->create([
            'tenant_id'         => $this->tenantA->id,
            'email_verified_at' => now(),
            'role'              => 'owner',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id'         => $this->tenantB->id,
            'email_verified_at' => now(),
            'role'              => 'owner',
        ]);

        $this->appA = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'name'         => 'Store Alpha App',
            'platform'     => 'woo',
            'package_name' => 'com.alpha.store',
            'app_key'      => 'app_alpha_key',
            'setup_step'   => 'platform',
            'status'       => 'draft',
        ]);

        $this->appB = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantB->id,
            'name'         => 'Store Beta App',
            'platform'     => 'woo',
            'package_name' => 'com.beta.store',
            'app_key'      => 'app_beta_key',
            'setup_step'   => 'connect',
            'status'       => 'draft',
        ]);

        $this->tokenA = $this->userA->createToken('dashboard')->plainTextToken;
    }

    public function test_setup_state_endpoint_returns_wizard_progress_and_blockers(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/apps/{$this->appA->ulid}/setup-state");

        $response->assertStatus(200)
            ->assertJson([
                'current_step'   => 'platform',
                'allowed_steps'  => ['platform'],
                'build_allowed'  => false,
                'build_blockers' => [
                    'CONNECTION_NOT_VERIFIED',
                    'CONFIG_NOT_PUBLISHED',
                    'SUBSCRIPTION_NOT_ACTIVE',
                ],
            ]);
    }

    public function test_skipping_steps_via_direct_api_calls_returns_403_step_locked(): void
    {
        // App A is on step 'platform' (order 0)
        $this->assertEquals(SetupStep::Platform, $this->appA->setup_step);

        // Attempt to call saveDraft directly (requires step 'theme', order 4)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/apps/{$this->appA->ulid}/config/draft", [
                'theme' => ['layout' => 'theme_a'],
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code'          => 'STEP_LOCKED',
                    'current_step'  => 'platform',
                    'required_step' => 'theme',
                ],
            ]);
    }

    public function test_changing_platform_resets_connection_and_invalidates_later_steps(): void
    {
        // Advance app to 'analysis' with a verified connection
        $this->appA->update(['setup_step' => SetupStep::Analysis]);

        Connection::withoutTenantScope()->create([
            'tenant_id'   => $this->tenantA->id,
            'app_id'      => $this->appA->id,
            'platform'    => 'woo',
            'site_url'    => 'https://alpha.com',
            'credentials' => ['ck' => '123', 'cs' => '456'],
            'status'      => ConnectionStatus::Verified,
        ]);

        $this->assertDatabaseHas('connections', ['app_id' => $this->appA->id]);

        // User switches platform from 'woo' to 'shopify'
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/platform", [
                'platform' => 'shopify',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'platform'     => 'shopify',
                'current_step' => 'connect',
            ]);

        // Old WooCommerce connection is wiped
        $this->assertDatabaseMissing('connections', [
            'app_id'     => $this->appA->id,
            'deleted_at' => null,
        ]);

        // Setup step rolled back to connect
        $this->appA->refresh();
        $this->assertEquals(SetupStep::Connect, $this->appA->setup_step);
    }

    public function test_rollback_invalidates_dependent_step_artifacts(): void
    {
        // Advance app to theme step with verified connection
        $this->appA->update(['setup_step' => SetupStep::Theme]);

        $connection = Connection::withoutTenantScope()->create([
            'tenant_id'   => $this->tenantA->id,
            'app_id'      => $this->appA->id,
            'platform'    => 'woo',
            'site_url'    => 'https://alpha.com',
            'credentials' => ['ck' => '123', 'cs' => '456'],
            'status'      => ConnectionStatus::Verified,
        ]);

        // Roll back to connect step
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/rollback", [
                'target_step' => 'connect',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'current_step' => 'connect',
            ]);

        // Connection status reset to not_started
        $connection->refresh();
        $this->assertEquals(ConnectionStatus::NotStarted, $connection->status);

        // App state updated
        $this->appA->refresh();
        $this->assertEquals(SetupStep::Connect, $this->appA->setup_step);
    }

    public function test_cross_tenant_access_blocked_with_404(): void
    {
        // User A tries to view User B's setup state
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/apps/{$this->appB->ulid}/setup-state");
        $res1->assertStatus(404);

        // User A tries to change platform on User B's app
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appB->ulid}/platform", [
                'platform' => 'shopify',
            ]);
        $res2->assertStatus(404);

        // User A tries to rollback User B's app
        $res3 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appB->ulid}/rollback", [
                'target_step' => 'platform',
            ]);
        $res3->assertStatus(404);
    }
}
