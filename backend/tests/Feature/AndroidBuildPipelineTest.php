<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BuildStatus;
use App\Models\App;
use App\Models\AppConfig;
use App\Models\Build;
use App\Models\Connection;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Build\Firebase\FakeFirebaseProvisioner;
use App\Services\Build\KeystoreService;
use App\Services\Build\Worker\BuildWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AndroidBuildPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private App $appA;
    private App $appB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();

        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant Alpha']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant Beta']);

        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Alice Alpha',
            'email'     => 'alice@alpha.com',
            'role'      => 'owner',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Bob Beta',
            'email'     => 'bob@beta.com',
            'role'      => 'owner',
        ]);

        $this->appA = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'name'         => 'Alpha Store App',
            'platform'     => 'woo',
            'package_name' => 'com.alpha.store',
            'app_key'      => 'app_key_alpha_777',
            'setup_step'   => 'build',
            'status'       => 'active',
        ]);

        $this->appB = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantB->id,
            'name'         => 'Beta Store App',
            'platform'     => 'woo',
            'package_name' => 'com.beta.store',
            'app_key'      => 'app_key_beta_888',
            'setup_step'   => 'build',
            'status'       => 'active',
        ]);

        // Connection verified for App A
        Connection::withoutTenantScope()->create([
            'tenant_id'             => $this->tenantA->id,
            'app_id'                => $this->appA->id,
            'site_url'              => 'https://alphastore.com',
            'consumer_key_encrypted'=> 'enc_ck_a',
            'consumer_secret_encrypted' => 'enc_cs_a',
            'status'                => 'verified',
            'verified_at'           => now(),
        ]);

        // Published configuration snapshot for App A
        AppConfig::withoutTenantScope()->create([
            'tenant_id'   => $this->tenantA->id,
            'app_id'      => $this->appA->id,
            'version'     => 1,
            'state'       => 'published',
            'config'      => [
                'theme' => ['colors' => ['primary' => '#4F46E5']],
            ],
            'published_at'=> now(),
        ]);

        $plan = \App\Models\Plan::create([
            'name'          => 'Pro',
            'price_monthly' => 199900,
            'features'      => ['builds' => true],
            'limits'        => ['builds_per_month' => 10],
            'is_active'     => true,
        ]);

        \App\Models\Subscription::create([
            'tenant_id'          => $this->tenantA->id,
            'app_id'             => $this->appA->id,
            'plan_id'            => $plan->id,
            'status'             => 'active',
            'current_period_end' => now()->addDays(30),
        ]);
    }

    /**
     * Test: Idempotency (double-clicking the trigger button creates only one in-flight build).
     */
    public function test_idempotency_double_click_creates_only_one_build(): void
    {
        // First click
        $response1 = $this->actingAs($this->userA)
            ->postJson("/api/apps/{$this->appA->ulid}/builds");

        $response1->assertStatus(201);
        $buildId1 = $response1->json('build.id');

        // Immediate second click (simulating double click or parallel client request)
        $response2 = $this->actingAs($this->userA)
            ->postJson("/api/apps/{$this->appA->ulid}/builds");

        $response2->assertStatus(201);
        $buildId2 = $response2->json('build.id');

        // Both calls return the exact same in-flight build ID
        $this->assertEquals($buildId1, $buildId2);
        $this->assertEquals(1, Build::withoutTenantScope()->where('app_id', $this->appA->id)->count());
    }

    /**
     * Test: Build worker state transitions (Queued -> Preparing -> Building -> Signing -> Ready).
     */
    public function test_build_worker_state_transitions_to_ready(): void
    {
        $build = Build::withoutTenantScope()->create([
            'tenant_id'      => $this->tenantA->id,
            'app_id'         => $this->appA->id,
            'config_version' => 1,
            'status'         => BuildStatus::Queued,
            'version_code'   => 1,
            'version_name'   => '1.0.0',
        ]);

        $keystoreService = new KeystoreService();
        $firebaseProvisioner = new FakeFirebaseProvisioner();
        $worker = new BuildWorker($keystoreService, $firebaseProvisioner);

        $worker->execute($build);

        $build->refresh();

        // Must reach terminal Ready status
        $this->assertEquals(BuildStatus::Ready, $build->status);
        $this->assertNotNull($build->started_at);
        $this->assertNotNull($build->finished_at);
        $this->assertNotNull($build->artifact_path);
        $this->assertNotNull($build->logs_path);
        $this->assertNull($build->error);

        // Assert artifact was stored in private storage
        $this->assertTrue(Storage::disk('local')->exists($build->artifact_path));
        $this->assertTrue(Storage::disk('local')->exists($build->logs_path));

        // Assert logs are sanitized (no plaintext secrets)
        $logContent = Storage::disk('local')->get($build->logs_path);
        $this->assertStringNotContainsString($this->appA->app_key, $logContent);
    }

    /**
     * Test: Build worker transitions to Failed on invalid package name.
     */
    public function test_build_worker_fails_gracefully_on_invalid_package_name(): void
    {
        // Inject invalid package name
        $this->appA->update(['package_name' => 'invalid-package-with-hyphens!']);

        $build = Build::withoutTenantScope()->create([
            'tenant_id'      => $this->tenantA->id,
            'app_id'         => $this->appA->id,
            'config_version' => 1,
            'status'         => BuildStatus::Queued,
            'version_code'   => 1,
            'version_name'   => '1.0.0',
        ]);

        $worker = new BuildWorker(new KeystoreService(), new FakeFirebaseProvisioner());
        $worker->execute($build);

        $build->refresh();
        $this->assertEquals(BuildStatus::Failed, $build->status);
        $this->assertStringContainsString('Invalid Android package name format', (string) $build->error);
    }

    /**
     * Test: Tenant isolation and permission checks.
     * Tenant A cannot trigger, view, or download Tenant B's builds.
     */
    public function test_tenant_a_cannot_access_or_download_tenant_b_builds(): void
    {
        // 1. Tenant A attempts to trigger build on Tenant B's app -> 404 NOT FOUND
        $this->actingAs($this->userA)
            ->postJson("/api/apps/{$this->appB->ulid}/builds")
            ->assertStatus(404);

        // 2. Create build for Tenant B
        $buildB = Build::withoutTenantScope()->create([
            'tenant_id'      => $this->tenantB->id,
            'app_id'         => $this->appB->id,
            'config_version' => 1,
            'status'         => BuildStatus::Ready,
            'artifact_path'  => 'artifacts/apps/beta/app-release.aab',
            'version_code'   => 1,
            'version_name'   => '1.0.0',
        ]);

        Storage::disk('local')->put($buildB->artifact_path, 'MOCK_BETA_AAB');

        // 3. Tenant A attempts to view Tenant B's build -> 404
        $this->actingAs($this->userA)
            ->getJson("/api/apps/{$this->appB->ulid}/builds/{$buildB->id}")
            ->assertStatus(404);

        // 4. Tenant A attempts to request download URL for Tenant B's build -> 404
        $this->actingAs($this->userA)
            ->getJson("/api/apps/{$this->appB->ulid}/builds/{$buildB->id}/download")
            ->assertStatus(404);
    }

    /**
     * Test: Download endpoint returns an expiring signed URL for ready builds.
     */
    public function test_download_endpoint_returns_expiring_signed_url(): void
    {
        $buildA = Build::withoutTenantScope()->create([
            'tenant_id'      => $this->tenantA->id,
            'app_id'         => $this->appA->id,
            'config_version' => 1,
            'status'         => BuildStatus::Ready,
            'artifact_path'  => 'artifacts/apps/alpha/app-release-v1.aab',
            'version_code'   => 1,
            'version_name'   => '1.0.0',
        ]);

        Storage::disk('local')->put($buildA->artifact_path, 'VALID_AAB_BINARY_CONTENT');

        $response = $this->actingAs($this->userA)
            ->getJson("/api/apps/{$this->appA->ulid}/builds/{$buildA->id}/download");

        $response->assertOk();
        $response->assertJsonStructure([
            'download_url',
            'expires_at',
            'version_code',
            'version_name',
        ]);

        $downloadUrl = $response->json('download_url');
        $this->assertStringContainsString('signature=', $downloadUrl);

        // Following the signed URL delivers the binary package
        $fileResponse = $this->get($downloadUrl);
        $fileResponse->assertOk();
        $this->assertEquals('VALID_AAB_BINARY_CONTENT', $fileResponse->streamedContent());
    }
}
