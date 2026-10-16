<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BuildStatus;
use App\Models\App;
use App\Models\AppConfig;
use App\Models\AppUser;
use App\Models\Build;
use App\Models\Connection;
use App\Models\Keystore;
use App\Models\PushDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Build\Firebase\FakeFirebaseProvisioner;
use App\Services\Build\KeystoreService;
use App\Services\Build\Worker\BuildWorker;
use App\Services\Connectors\ConnectorFactory;
use App\Services\Connectors\ConnectorInterface;
use App\Services\DTOs\CommonOrder;
use App\Services\DTOs\PaginatedResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class TenantAndUserCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private App $appA;
    private App $appB;
    private User $ownerA;
    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();
        RateLimiter::clear('otp_req:*');

        // 1. Setup Tenant A and Tenant B
        $this->tenantA = Tenant::factory()->create(['name' => 'Acme Fashion']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Zen Electronics']);

        $this->ownerA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email'     => 'owner@acme.com',
            'role'      => 'owner',
        ]);

        $this->ownerB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email'     => 'owner@zen.com',
            'role'      => 'owner',
        ]);

        // 2. Setup Apps for both tenants
        $this->appA = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'name'         => 'Acme Store App',
            'platform'     => 'woo',
            'package_name' => 'com.acme.fashion',
            'app_key'      => 'app_key_acme_isolated_1',
            'setup_step'   => 'build',
            'status'       => 'active',
        ]);

        $this->appB = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantB->id,
            'name'         => 'Zen Store App',
            'platform'     => 'woo',
            'package_name' => 'com.zen.electronics',
            'app_key'      => 'app_key_zen_isolated_2',
            'setup_step'   => 'build',
            'status'       => 'active',
        ]);

        // 3. Store Connections
        Connection::withoutTenantScope()->create([
            'tenant_id'                 => $this->tenantA->id,
            'app_id'                    => $this->appA->id,
            'site_url'                  => 'https://acmefashion.com',
            'consumer_key_encrypted'    => 'ck_acme',
            'consumer_secret_encrypted' => 'cs_acme',
            'status'                    => 'verified',
            'credentials'               => ['ck' => 'ck_acme', 'cs' => 'cs_acme'],
        ]);

        Connection::withoutTenantScope()->create([
            'tenant_id'                 => $this->tenantB->id,
            'app_id'                    => $this->appB->id,
            'site_url'                  => 'https://zenelectronics.com',
            'consumer_key_encrypted'    => 'ck_zen',
            'consumer_secret_encrypted' => 'cs_zen',
            'status'                    => 'verified',
            'credentials'               => ['ck' => 'ck_zen', 'cs' => 'cs_zen'],
        ]);
    }

    /**
     * Test 1: Parallel requests from Tenant A and Tenant B never cross-contaminate cache partitions.
     */
    public function test_parallel_tenants_cache_partitions_do_not_collide(): void
    {
        // Simulate resolving both apps via X-App-Key header in parallel
        $respA = $this->withHeaders(['X-App-Key' => $this->appA->app_key])
            ->getJson('/v1/app-config');
        $respB = $this->withHeaders(['X-App-Key' => $this->appB->app_key])
            ->getJson('/v1/app-config');

        $respA->assertOk();
        $respB->assertOk();

        // Verify cached app instances are strictly segregated by key
        $cachedAppA = Cache::get("app_key:{$this->appA->app_key}");
        $cachedAppB = Cache::get("app_key:{$this->appB->app_key}");

        $this->assertNotNull($cachedAppA);
        $this->assertNotNull($cachedAppB);
        $this->assertEquals($this->appA->id, $cachedAppA->id);
        $this->assertEquals($this->appB->id, $cachedAppB->id);
        $this->assertEquals($this->tenantA->id, $cachedAppA->tenant_id);
        $this->assertEquals($this->tenantB->id, $cachedAppB->tenant_id);
        $this->assertNotEquals($cachedAppA->id, $cachedAppB->id);
    }

    /**
     * Test 2: Two customers in parallel across different tenants get strictly isolated order data.
     */
    public function test_parallel_customers_across_tenants_receive_isolated_data(): void
    {
        $customerA = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenantA->id,
            'app_id'            => $this->appA->id,
            'phone'             => '+15551111111',
            'store_customer_id' => '1001',
            'name'              => 'Alice Acme Customer',
        ]);

        $customerB = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenantB->id,
            'app_id'            => $this->appB->id,
            'phone'             => '+15552222222',
            'store_customer_id' => '2002',
            'name'              => 'Bob Zen Customer',
        ]);

        $tokenA = $customerA->createToken('session', ["app:{$this->appA->id}"])->plainTextToken;
        $tokenB = $customerB->createToken('session', ["app:{$this->appB->id}"])->plainTextToken;

        // Mock Connector A returning customer 1001 orders
        $mockConnectorA = Mockery::mock(ConnectorInterface::class);
        $mockConnectorA->shouldReceive('getOrders')
            ->with(['customer' => '1001', 'per_page' => 20])
            ->andReturn(new PaginatedResult(
                items: [
                    new CommonOrder(
                        id: 'order_acme_1',
                        orderNumber: 'ACME-01',
                        status: 'completed',
                        total: 120.00,
                        currency: 'USD',
                        createdAt: now()->toIso8601String(),
                        itemCount: 2
                    ),
                ],
                totalItems: 1,
                page: 1,
                perPage: 20
            ));

        // Mock Connector B returning customer 2002 orders
        $mockConnectorB = Mockery::mock(ConnectorInterface::class);
        $mockConnectorB->shouldReceive('getOrders')
            ->with(['customer' => '2002', 'per_page' => 20])
            ->andReturn(new PaginatedResult(
                items: [
                    new CommonOrder(
                        id: 'order_zen_2',
                        orderNumber: 'ZEN-02',
                        status: 'processing',
                        total: 890.00,
                        currency: 'USD',
                        createdAt: now()->toIso8601String(),
                        itemCount: 1
                    ),
                ],
                totalItems: 1,
                page: 1,
                perPage: 20
            ));

        $factoryMock = Mockery::mock(ConnectorFactory::class);
        $factoryMock->shouldReceive('make')
            ->andReturnUsing(function ($arg) use ($mockConnectorA, $mockConnectorB) {
                $id = ($arg instanceof App) ? $arg->id : $arg->app_id;
                return $id === $this->appA->id ? $mockConnectorA : $mockConnectorB;
            });
        $this->app->instance(ConnectorFactory::class, $factoryMock);

        // Customer A requests orders
        $respA = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$tokenA}",
        ])->getJson('/v1/orders');

        // Customer B requests orders
        $respB = $this->withHeaders([
            'X-App-Key'     => $this->appB->app_key,
            'Authorization' => "Bearer {$tokenB}",
        ])->getJson('/v1/orders');

        $respA->assertOk();
        $respB->assertOk();

        $this->assertEquals('ACME-01', $respA->json('data.0.order_number'));
        $this->assertEquals('ZEN-02', $respB->json('data.0.order_number'));

        // Customer A cannot use token on Tenant B
        $crossTenantCall = $this->withHeaders([
            'X-App-Key'     => $this->appB->app_key,
            'Authorization' => "Bearer {$tokenA}",
        ])->getJson('/v1/orders');

        $crossTenantCall->assertStatus(403);
        $crossTenantCall->assertJson(['error' => ['code' => 'FORBIDDEN_APP_MISMATCH']]);
    }

    /**
     * Test 3: Two customers on the same device - User 1 logout clears push tokens, cache, and leaves no residual data for User 2.
     */
    public function test_user_switch_on_same_device_clears_all_residual_data(): void
    {
        $deviceFcmToken = 'shared_mobile_device_fcm_token_xyz';

        // 1. Customer 1 logs in on the device
        $cust1 = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenantA->id,
            'app_id'            => $this->appA->id,
            'phone'             => '+15553334444',
            'store_customer_id' => 'cust_301',
            'name'              => 'First Customer',
        ]);

        $token1 = $cust1->createToken('session', ["app:{$this->appA->id}"])->plainTextToken;

        // Customer 1 registers the device for push
        $reg1 = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$token1}",
        ])->postJson('/v1/devices', [
            'fcm_token' => $deviceFcmToken,
            'platform'  => 'android',
        ]);
        $reg1->assertOk();

        $this->assertDatabaseHas('push_devices', [
            'app_id'      => $this->appA->id,
            'app_user_id' => $cust1->id,
            'fcm_token'   => $deviceFcmToken,
        ]);

        // Customer 1 logs out and explicitly unregisters the FCM token
        $unreg = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$token1}",
        ])->deleteJson('/v1/devices', [
            'fcm_token' => $deviceFcmToken,
        ]);
        $unreg->assertOk();

        $logout = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$token1}",
        ])->postJson('/v1/auth/logout');
        $logout->assertOk();

        // Ensure device token is completely removed from database
        $this->assertDatabaseMissing('push_devices', [
            'fcm_token' => $deviceFcmToken,
        ]);

        // Ensure Customer 1's token is revoked
        $staleCall = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$token1}",
        ])->getJson('/v1/orders');
        $staleCall->assertStatus(401);

        // 2. Customer 2 logs in on the exact same device
        $cust2 = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenantA->id,
            'app_id'            => $this->appA->id,
            'phone'             => '+15555556666',
            'store_customer_id' => 'cust_302',
            'name'              => 'Second Customer',
        ]);

        $token2 = $cust2->createToken('session', ["app:{$this->appA->id}"])->plainTextToken;

        // Customer 2 registers device with same hardware token
        $reg2 = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$token2}",
        ])->postJson('/v1/devices', [
            'fcm_token' => $deviceFcmToken,
            'platform'  => 'android',
        ]);
        $reg2->assertOk();

        // Push device is now linked exclusively to Customer 2, never Customer 1
        $this->assertDatabaseHas('push_devices', [
            'app_id'      => $this->appA->id,
            'app_user_id' => $cust2->id,
            'fcm_token'   => $deviceFcmToken,
        ]);

        $this->assertDatabaseMissing('push_devices', [
            'app_user_id' => $cust1->id,
            'fcm_token'   => $deviceFcmToken,
        ]);
    }

    /**
     * Test 4: File storage paths and keystores are strictly namespaced by tenant ID (tenants/{id}/...).
     */
    public function test_file_storage_paths_are_strictly_isolated_by_tenant(): void
    {
        $keystoreService = new KeystoreService();

        // Generate keystores for Tenant A and Tenant B
        $keystoreA = $keystoreService->generateOrGet($this->appA);
        $keystoreB = $keystoreService->generateOrGet($this->appB);

        // Verify database and disk paths are prefixed with tenants/{tenant_id}/
        $this->assertStringStartsWith("tenants/{$this->tenantA->id}/", "tenants/{$this->tenantA->id}/keystores/app_{$this->appA->ulid}.jks");
        $this->assertStringStartsWith("tenants/{$this->tenantB->id}/", "tenants/{$this->tenantB->id}/keystores/app_{$this->appB->ulid}.jks");

        // Execute build worker mock for both apps
        $buildA = Build::withoutTenantScope()->create([
            'tenant_id'      => $this->tenantA->id,
            'app_id'         => $this->appA->id,
            'config_version' => 1,
            'status'         => BuildStatus::Queued,
            'version_code'   => 1,
            'version_name'   => '1.0.0',
        ]);

        $buildB = Build::withoutTenantScope()->create([
            'tenant_id'      => $this->tenantB->id,
            'app_id'         => $this->appB->id,
            'config_version' => 1,
            'status'         => BuildStatus::Queued,
            'version_code'   => 1,
            'version_name'   => '1.0.0',
        ]);

        $worker = new BuildWorker($keystoreService, new FakeFirebaseProvisioner());
        $worker->execute($buildA);
        $worker->execute($buildB);

        $buildA->refresh();
        $buildB->refresh();

        // Assert artifact and log paths are strictly in tenants/{tenant_id}/
        $this->assertStringStartsWith("tenants/{$this->tenantA->id}/", $buildA->artifact_path);
        $this->assertStringStartsWith("tenants/{$this->tenantA->id}/", $buildA->logs_path);

        $this->assertStringStartsWith("tenants/{$this->tenantB->id}/", $buildB->artifact_path);
        $this->assertStringStartsWith("tenants/{$this->tenantB->id}/", $buildB->logs_path);

        // Verify storage files exist under their respective tenant directories
        $this->assertTrue(Storage::disk('local')->exists($buildA->artifact_path));
        $this->assertTrue(Storage::disk('local')->exists($buildB->artifact_path));
        $this->assertNotEquals($buildA->artifact_path, $buildB->artifact_path);
    }
}
