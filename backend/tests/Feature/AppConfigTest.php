<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConnectionStatus;
use App\Enums\SetupStep;
use App\Models\App;
use App\Models\AppConfig;
use App\Models\Connection;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Security\UrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\WooFixtures;
use Tests\TestCase;

class AppConfigTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private App $appA;
    private App $appB;
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        UrlGuard::setDnsResolver(fn () => ['93.184.216.34']);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = User::factory()->create([
            'tenant_id'         => $this->tenantA->id,
            'email_verified_at' => now(),
            'role'              => 'owner',
        ]);

        // App A is at theme step
        $this->appA = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'name'         => 'Alpha Store App',
            'platform'     => 'woo',
            'package_name' => 'com.alpha.app',
            'app_key'      => 'app_alpha_key',
            'setup_step'   => 'theme',
            'status'       => 'draft',
        ]);

        $this->appB = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantB->id,
            'name'         => 'Beta Store App',
            'platform'     => 'woo',
            'package_name' => 'com.beta.app',
            'app_key'      => 'app_beta_key',
            'setup_step'   => 'theme',
            'status'       => 'draft',
        ]);

        $this->tokenA = $this->userA->createToken('dashboard')->plainTextToken;
    }

    protected function tearDown(): void
    {
        UrlGuard::resetDnsResolver();
        parent::tearDown();
    }

    private function validConfigPayload(): array
    {
        return [
            'schema_version' => 1,
            'app' => [
                'name'     => 'Alpha Store',
                'currency' => 'INR',
            ],
            'theme' => [
                'layout' => 'theme_a',
                'colors' => [
                    'primary'    => '#E91E63',
                    'secondary'  => '#222222',
                    'background' => '#FFFFFF',
                    'surface'    => '#FAFAFA',
                    'text'       => '#111111',
                ],
                'radius' => 12,
                'font'   => 'Poppins',
                'nav'    => 'bottom',
            ],
            'features' => [
                'wishlist'      => true,
                'otp_login'     => true,
                'google_login'  => true,
                'notifications' => true,
                'reviews'       => false,
            ],
            'home' => [
                [
                    'type'  => 'banner',
                    'items' => [
                        [
                            'image' => 'https://assets.webintoapp.com/banner.jpg',
                            'link'  => 'category:12',
                        ],
                    ],
                ],
            ],
            'links' => [
                'privacy' => 'https://alpha.com/privacy',
                'terms'   => 'https://alpha.com/terms',
            ],
        ];
    }

    public function test_save_draft_config_and_advances_step(): void
    {
        $payload = $this->validConfigPayload();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/apps/{$this->appA->ulid}/config/draft", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Draft configuration saved successfully.',
                'state'   => 'draft',
                'config'  => [
                    'theme' => [
                        'layout' => 'theme_a',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('app_configs', [
            'app_id' => $this->appA->id,
            'state'  => 'draft',
        ]);

        $this->appA->refresh();
        $this->assertEquals(SetupStep::Branding, $this->appA->setup_step);
    }

    public function test_tampered_config_payload_rejected_with_422(): void
    {
        $payload = $this->validConfigPayload();
        // Tamper colors with invalid non-hex value
        $payload['theme']['colors']['primary'] = 'rgb(255, 0, 0)';

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/apps/{$this->appA->ulid}/config/draft", $payload);

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'CONFIG_INVALID',
                ],
            ]);
    }

    public function test_publish_config_creates_incrementing_versions(): void
    {
        // 1. Save draft
        $payload = $this->validConfigPayload();
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/apps/{$this->appA->ulid}/config/draft", $payload);

        // 2. Publish draft (Version 1)
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/config/publish");

        $res1->assertStatus(200)
            ->assertJson([
                'version' => 1,
                'message' => 'Configuration published successfully.',
            ]);

        $this->assertDatabaseHas('app_configs', [
            'app_id'  => $this->appA->id,
            'version' => 1,
            'state'   => 'published',
        ]);

        // 3. Update draft and publish again (Version 2)
        $payload['theme']['colors']['primary'] = '#00FF00';
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/apps/{$this->appA->ulid}/config/draft", $payload);

        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/config/publish");

        $res2->assertStatus(200)
            ->assertJson([
                'version' => 2,
            ]);

        $this->assertDatabaseHas('app_configs', [
            'app_id'  => $this->appA->id,
            'version' => 2,
            'state'   => 'published',
        ]);
    }

    public function test_preview_data_returns_real_products_from_connector(): void
    {
        // Advance step to connect and configure verified connection
        $this->appA->update(['setup_step' => SetupStep::Theme]);

        Connection::withoutTenantScope()->create([
            'tenant_id'   => $this->tenantA->id,
            'app_id'      => $this->appA->id,
            'platform'    => 'woo',
            'site_url'    => 'https://acme-organics.com',
            'credentials' => [
                'consumer_key'    => 'ck_test',
                'consumer_secret' => 'cs_test',
            ],
            'status'      => ConnectionStatus::Verified,
        ]);

        Http::fake([
            'https://acme-organics.com/wp-json/wc/v3/products?page=1&per_page=10' => Http::response(
                [WooFixtures::product(101, 'Organic Rose Mist')],
                200,
                ['X-WP-Total' => '1', 'X-WP-TotalPages' => '1']
            ),
            'https://acme-organics.com/wp-json/wc/v3/products/categories*' => Http::response(
                WooFixtures::categories(),
                200
            ),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/apps/{$this->appA->ulid}/preview-data");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'products' => [
                    '*' => ['id', 'title', 'price', 'in_stock'],
                ],
                'categories',
                'total',
            ]);

        $this->assertEquals('101', $response->json('products.0.id'));
        $this->assertEquals('Organic Rose Mist', $response->json('products.0.title'));
    }

    public function test_preview_data_fails_if_connection_is_not_verified(): void
    {
        // No connection created yet
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/apps/{$this->appA->ulid}/preview-data");

        $response->assertStatus(400)
            ->assertJson([
                'error' => [
                    'code' => 'CONNECTION_NOT_VERIFIED',
                ],
            ]);
    }

    public function test_build_allowed_is_accurately_computed(): void
    {
        // 1. Initial: build_allowed is false
        $this->assertFalse($this->appA->isBuildAllowed());

        // 2. Add verified connection: still false (no published config, no sub)
        $conn = Connection::withoutTenantScope()->create([
            'tenant_id'   => $this->tenantA->id,
            'app_id'      => $this->appA->id,
            'platform'    => 'woo',
            'site_url'    => 'https://acme.com',
            'credentials' => ['ck' => 'k', 'cs' => 's'],
            'status'      => ConnectionStatus::Verified,
        ]);
        $this->appA->refresh();
        $this->assertFalse($this->appA->isBuildAllowed());

        // 3. Add published config: still false (no active subscription)
        AppConfig::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'app_id'       => $this->appA->id,
            'version'      => 1,
            'state'        => 'published',
            'config'       => $this->validConfigPayload(),
            'published_at' => now(),
        ]);
        $this->appA->refresh();
        $this->assertFalse($this->appA->isBuildAllowed());

        // 4. Add active subscription: build_allowed becomes TRUE!
        $plan = Plan::create([
            'name'          => 'Pro',
            'price_monthly' => 299900,
            'features'      => ['push' => true],
            'limits'        => ['builds' => 5],
        ]);

        Subscription::withoutTenantScope()->create([
            'tenant_id'          => $this->tenantA->id,
            'app_id'             => $this->appA->id,
            'plan_id'            => $plan->id,
            'status'             => 'active',
            'razorpay_sub_id'    => 'sub_active_123',
            'current_period_end' => now()->addMonth(),
        ]);

        $this->appA->refresh();
        $this->assertTrue($this->appA->isBuildAllowed());
        $this->assertEmpty($this->appA->getBuildBlockers());
    }

    public function test_cross_tenant_access_blocked_with_404(): void
    {
        // Tenant A tries to access Tenant B's draft
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/apps/{$this->appB->ulid}/config/draft");
        $res1->assertStatus(404);

        // Tenant A tries to save draft on Tenant B's app
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/apps/{$this->appB->ulid}/config/draft", $this->validConfigPayload());
        $res2->assertStatus(404);

        // Tenant A tries to publish on Tenant B's app
        $res3 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appB->ulid}/config/publish");
        $res3->assertStatus(404);
    }
}
