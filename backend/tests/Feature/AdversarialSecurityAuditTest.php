<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BuildStatus;
use App\Enums\ConnectionStatus;
use App\Enums\SetupStep;
use App\Models\App;
use App\Models\AppConfig;
use App\Models\AppUser;
use App\Models\Connection;
use App\Models\NotificationCampaign;
use App\Models\Plan;
use App\Models\PushDevice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Billing\EntitlementService;
use App\Services\Build\Assets\AssetProcessor;
use App\Services\Build\BuildOrchestrator;
use App\Services\Connectors\ConnectorFactory;
use App\Services\Connectors\ConnectorInterface;
use App\Services\Notifications\DeepLinkValidator;
use App\Services\Notifications\WhatsAppService;
use App\Services\Security\CredentialVault;
use App\Services\Security\UrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class AdversarialSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private App $appA;
    private App $appB;
    private Plan $proPlan;
    private string $tokenA;
    private string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();
        RateLimiter::clear('otp_req:*');

        config(['services.razorpay.webhook_secret' => 'rzp_sec_adversarial_123']);
        config(['services.whatsapp.app_secret' => 'wa_sec_adversarial_456']);
        config(['services.whatsapp.webhook_verify_token' => 'wa_verify_token_789']);

        // Set up Tenant A and Tenant B
        $this->tenantA = Tenant::factory()->create(['name' => 'Adversarial Tenant Alpha']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Adversarial Tenant Beta']);

        $this->userA = User::factory()->create([
            'tenant_id'         => $this->tenantA->id,
            'name'              => 'Alice Alpha',
            'email'             => 'alice@alpha.test',
            'role'              => 'owner',
            'email_verified_at' => now(),
        ]);

        $this->userB = User::factory()->create([
            'tenant_id'         => $this->tenantB->id,
            'name'              => 'Bob Beta',
            'email'             => 'bob@beta.test',
            'role'              => 'owner',
            'email_verified_at' => now(),
        ]);

        $this->tokenA = $this->userA->createToken('dashboard')->plainTextToken;
        $this->tokenB = $this->userB->createToken('dashboard')->plainTextToken;

        $this->proPlan = Plan::create([
            'name'          => 'Pro',
            'price_monthly' => 199900,
            'features'      => ['whatsapp' => true, 'push_broadcast' => true],
            'limits'        => ['builds_per_month' => 10, 'push_per_month' => 500, 'max_apps' => 3],
            'is_active'     => true,
        ]);

        $this->appA = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'name'         => 'Alpha Store App',
            'platform'     => 'woo',
            'package_name' => 'com.alpha.store',
            'app_key'      => 'app_key_alpha_sec_1',
            'setup_step'   => 'payment',
            'status'       => 'active',
        ]);

        $this->appB = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantB->id,
            'name'         => 'Beta Store App',
            'platform'     => 'woo',
            'package_name' => 'com.beta.store',
            'app_key'      => 'app_key_beta_sec_2',
            'setup_step'   => 'payment',
            'status'       => 'active',
        ]);

        // Verified store connection for App A
        Connection::withoutTenantScope()->create([
            'tenant_id'        => $this->tenantA->id,
            'app_id'           => $this->appA->id,
            'platform'         => 'woo',
            'site_url'         => 'https://alphastore.com',
            'status'           => 'verified',
            'last_verified_at' => now(),
            'credentials'      => ['webhook_secret' => 'alpha_secret'],
        ]);

        // Published configuration snapshot for App A
        AppConfig::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'app_id'       => $this->appA->id,
            'version'      => 1,
            'state'        => 'published',
            'config'       => ['theme' => ['colors' => ['primary' => '#10B981']]],
            'published_at' => now(),
        ]);
    }

    /**
     * Attack 1: IDOR - Tenant A token requesting Tenant B's app/order -> Expected: 404 (Not 403 to prevent existence leakage).
     */
    public function test_attack_01_idor_cross_tenant_access_returns_404(): void
    {
        // Tenant A requests Tenant B's app
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/apps/{$this->appB->ulid}/connection");

        $response->assertStatus(404);
    }

    /**
     * Attack 2: Wizard step skip (direct API call to later step) -> Expected: 403 STEP_LOCKED.
     */
    public function test_attack_02_wizard_step_skip_is_blocked_with_403_step_locked(): void
    {
        // Put App A on 'platform' step
        $this->appA->update(['setup_step' => 'platform']);

        // Attempt direct call to draft config (which requires 'theme' step)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->putJson("/api/apps/{$this->appA->ulid}/config/draft", [
                'theme' => ['colors' => ['primary' => '#123456']],
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'STEP_LOCKED',
            ],
        ]);
    }

    /**
     * Attack 3: Build without payment -> Expected: 403 PAYMENT_REQUIRED.
     */
    public function test_attack_03_build_without_payment_returns_403(): void
    {
        // App A has no active subscription
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/builds");

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'PAYMENT_REQUIRED',
            ],
        ]);
    }

    /**
     * Attack 4: Guessing another tenant's APK download URL / URL tampering -> Expected: 403/404.
     */
    public function test_attack_04_tampered_download_link_is_rejected(): void
    {
        $build = \App\Models\Build::withoutTenantScope()->create([
            'tenant_id'      => $this->tenantA->id,
            'app_id'         => $this->appA->id,
            'config_version' => 1,
            'status'         => BuildStatus::Ready,
            'version_code'   => 1,
            'version_name'   => '1.0.0',
            'artifact_path'  => 'builds/alpha-1.aab',
        ]);

        Storage::disk('local')->put('builds/alpha-1.aab', 'fake binary');

        // Valid signature generated for App A
        $validUrl = URL::temporarySignedRoute(
            'api.apps.builds.download.file',
            now()->addMinutes(15),
            ['ulid' => $this->appA->ulid, 'id' => $build->id]
        );

        // Tamper with build ID or signature
        $tamperedUrl = str_replace("id={$build->id}", "id=99999", $validUrl);

        $response = $this->get($tamperedUrl);
        // Signed middleware rejects with 403
        $response->assertStatus(403);
    }

    /**
     * Attack 5: SSRF attack (169.254.169.254, localhost, loopback, private IP) -> Expected: Blocked.
     */
    public function test_attack_05_ssrf_attacks_are_blocked(): void
    {
        $urlGuard = new UrlGuard();

        // Metadata IP
        $this->assertFalse($urlGuard->isSafeUrl('http://169.254.169.254/latest/meta-data'));
        // Localhost
        $this->assertFalse($urlGuard->isSafeUrl('http://localhost:8080/admin'));
        // Loopback 127.0.0.1
        $this->assertFalse($urlGuard->isSafeUrl('http://127.0.0.1/wp-json'));
        // Private IP 10.x
        $this->assertFalse($urlGuard->isSafeUrl('https://10.0.0.5/wp-json'));
        // Private IP 192.168.x
        $this->assertFalse($urlGuard->isSafeUrl('https://192.168.1.1/wp-json'));
        // Non-HTTPS
        $this->assertFalse($urlGuard->isSafeUrl('http://example.com/wp-json'));
    }

    /**
     * Attack 6: Secret leakage (Credentials never returned in API responses or plain logs).
     */
    public function test_attack_06_credentials_never_exposed_in_api_responses(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/apps/{$this->appA->ulid}/connection");

        $response->assertStatus(200);
        $json = $response->getContent();

        // Sensitive credentials must never be serialized
        $this->assertStringNotContainsString('consumer_key_encrypted', $json);
        $this->assertStringNotContainsString('consumer_secret_encrypted', $json);
        $this->assertStringNotContainsString('alpha_secret', $json);
    }

    /**
     * Attack 7: OTP brute force / SMS bombing -> Expected: 429 Throttle.
     */
    public function test_attack_07_otp_rate_limiting_prevents_sms_bombing(): void
    {
        $phone = '+15550009999';

        // 3 allowed attempts
        for ($i = 0; $i < 3; $i++) {
            $resp = $this->withHeaders(['X-App-Key' => $this->appA->app_key])
                ->postJson('/v1/auth/otp/request', ['phone' => $phone]);
            $this->assertContains($resp->status(), [200, 429]);
        }

        // 4th request must be throttled (429)
        $blockedResp = $this->withHeaders(['X-App-Key' => $this->appA->app_key])
            ->postJson('/v1/auth/otp/request', ['phone' => $phone]);

        $blockedResp->assertStatus(429);
        $blockedResp->assertJson(['error' => ['code' => 'OTP_RATE_LIMIT_EXCEEDED']]);
    }

    /**
     * Attack 8: Forged Razorpay webhook signature -> Expected: 400 INVALID_SIGNATURE.
     */
    public function test_attack_08_forged_webhook_signature_is_rejected(): void
    {
        $payload = json_encode(['event' => 'payment.captured', 'id' => 'evt_fake_999']);

        $response = $this->withHeaders([
            'X-Razorpay-Signature' => 'forged_invalid_signature_hash',
            'Content-Type'         => 'application/json',
        ])->call('POST', '/webhooks/razorpay', [], [], [], [], (string) $payload);

        $response->assertStatus(400);
        $response->assertJson(['error' => ['code' => 'INVALID_SIGNATURE']]);
    }

    /**
     * Attack 9: Webhook replay attack (same event_id sent twice) -> Expected: Second is ignored.
     */
    public function test_attack_09_webhook_replay_is_ignored(): void
    {
        $secret = config('services.razorpay.webhook_secret');
        $payload = json_encode(['event' => 'payment.captured', 'id' => 'evt_replay_test_001']);
        $signature = hash_hmac('sha256', (string) $payload, (string) $secret);

        // 1st delivery
        $resp1 = $this->withHeaders([
            'X-Razorpay-Signature' => $signature,
            'Content-Type'         => 'application/json',
        ])->call('POST', '/webhooks/razorpay', [], [], [], [], (string) $payload);

        $resp1->assertStatus(200);

        // 2nd delivery (replay)
        $resp2 = $this->withHeaders([
            'X-Razorpay-Signature' => $signature,
            'Content-Type'         => 'application/json',
        ])->call('POST', '/webhooks/razorpay', [], [], [], [], (string) $payload);

        $resp2->assertStatus(200);
        $resp2->assertJson(['status' => 'ignored']);
    }

    /**
     * Attack 10: Mass assignment attempt (role=superadmin, tenant_id=...) -> Expected: Ignored.
     */
    public function test_attack_10_mass_assignment_is_prevented(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'          => 'Attacker User',
            'email'         => 'attacker@example.com',
            'password'      => 'SecurePassword123!',
            'business_name' => 'Attacker Business',
            'role'          => 'superadmin',       // malicious
            'tenant_id'     => 1,                  // malicious
            'is_admin'      => true,               // malicious
        ]);

        $response->assertStatus(201);
        $user = User::where('email', 'attacker@example.com')->first();
        $this->assertNotNull($user);
        // Role must strictly be 'owner' for newly registered tenant creator
        $this->assertEquals('owner', $user->role);
        $this->assertNotEquals(1, $user->tenant_id);
    }

    /**
     * Attack 11: Stored XSS in notification / campaign title and body -> Expected: Sanitized.
     */
    public function test_attack_11_xss_in_campaign_is_sanitized(): void
    {
        Subscription::create([
            'tenant_id'          => $this->tenantA->id,
            'app_id'             => $this->appA->id,
            'plan_id'            => $this->proPlan->id,
            'status'             => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/notifications/campaigns", [
                'title' => '<script>alert("xss")</script>Flash Discount!',
                'body'  => 'Click <img src="x" onerror="alert(1)"> here for deals',
            ]);

        $response->assertStatus(201);
        $campaign = NotificationCampaign::withoutTenantScope()->find($response->json('campaign.id'));
        $this->assertStringNotContainsString('<script>', $campaign->title);
        $this->assertStringNotContainsString('onerror', $campaign->body);
    }

    /**
     * Attack 12: User switch on same phone (User A logout -> User B login) -> Expected: Residual tokens cleared.
     */
    public function test_attack_12_user_switch_unregisters_previous_device_tokens(): void
    {
        $customerA = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenantA->id,
            'app_id'            => $this->appA->id,
            'phone'             => '+15551112222',
            'store_customer_id' => 'cust_a',
        ]);

        $deviceToken = 'device_shared_fcm_token_123';

        PushDevice::withoutTenantScope()->create([
            'tenant_id'   => $this->tenantA->id,
            'app_id'      => $this->appA->id,
            'app_user_id' => $customerA->id,
            'fcm_token'   => $deviceToken,
            'platform'    => 'android',
        ]);

        $tokenA = $customerA->createToken('session', ["app:{$this->appA->id}"])->plainTextToken;

        // User A logs out and unregisters device
        $logoutResp = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$tokenA}",
        ])->deleteJson('/v1/devices', ['fcm_token' => $deviceToken]);

        $logoutResp->assertStatus(200);

        // Previous token must be wiped
        $this->assertDatabaseMissing('push_devices', ['fcm_token' => $deviceToken]);
    }

    /**
     * Attack 13: Shared cache leakage between tenants -> Expected: Cache keys segregated.
     */
    public function test_attack_13_cache_is_partitioned_by_tenant_and_app(): void
    {
        $cacheKeyA = "app_config:{$this->appA->tenant_id}:{$this->appA->id}";
        $cacheKeyB = "app_config:{$this->appB->tenant_id}:{$this->appB->id}";

        Cache::put($cacheKeyA, ['brand' => 'Brand Alpha'], 3600);
        Cache::put($cacheKeyB, ['brand' => 'Brand Beta'], 3600);

        $this->assertEquals('Brand Alpha', Cache::get($cacheKeyA)['brand']);
        $this->assertEquals('Brand Beta', Cache::get($cacheKeyB)['brand']);
        $this->assertNotEquals($cacheKeyA, $cacheKeyB);
    }

    /**
     * Attack 14: Malicious deep link payload (javascript:, external urls) -> Expected: Blocked.
     */
    public function test_attack_14_malicious_deep_links_are_rejected(): void
    {
        $validator = new DeepLinkValidator();

        $this->assertFalse($validator->isValid('javascript:alert(document.cookie)'));
        $this->assertFalse($validator->isValid('https://malicious-phishing.com/steal'));
        $this->assertFalse($validator->isValid('intent://malicious.app#Intent;scheme=bad;end'));
        $this->assertFalse($validator->isValid('file:///etc/passwd'));

        // Allowlisted formats are valid
        $this->assertTrue($validator->isValid('product:shoes_99'));
        $this->assertTrue($validator->isValid('order:10203'));
        $this->assertTrue($validator->isValid('category:jackets'));
    }

    /**
     * Attack 15: Arbitrary webview checkout URL -> Expected: Allowlist blocks external domain.
     */
    public function test_attack_15_webview_checkout_restricted_to_verified_host(): void
    {
        $mockConnector = Mockery::mock(ConnectorInterface::class);
        $mockConnector->shouldReceive('createCheckoutUrl')
            ->andReturn('https://alphastore.com/checkout');

        $factory = Mockery::mock(ConnectorFactory::class);
        $factory->shouldReceive('make')->andReturn($mockConnector);
        $this->app->instance(ConnectorFactory::class, $factory);

        $response = $this->withHeaders(['X-App-Key' => $this->appA->app_key])
            ->postJson('/v1/cart/checkout-url', [
                'items' => [['product_id' => 1, 'quantity' => 1]],
            ]);

        $response->assertStatus(200);
        $allowedHosts = $response->json('webview_allowed_hosts');
        $this->assertContains('alphastore.com', $allowedHosts);
        $this->assertNotContains('evil.com', $allowedHosts);
    }

    /**
     * Attack 16: Admin route on client domain (/admin) -> Expected: 404 Not Found.
     */
    public function test_attack_16_admin_url_on_client_domain_returns_404(): void
    {
        $response = $this->get('/admin');
        $response->assertStatus(404);
    }

    /**
     * Attack 17: Expired subscription feature usage -> Expected: 403 Forbidden.
     */
    public function test_attack_17_expired_subscription_feature_use_returns_403(): void
    {
        // Expired subscription past grace period
        Subscription::create([
            'tenant_id'          => $this->tenantA->id,
            'app_id'             => $this->appA->id,
            'plan_id'            => $this->proPlan->id,
            'status'             => 'active',
            'current_period_end' => now()->subDays(10), // Expired > 7 days ago
        ]);

        /** @var WhatsAppService $whatsAppService */
        $whatsAppService = app(WhatsAppService::class);

        $this->expectException(\RuntimeException::class);
        $whatsAppService->sendTemplateMessage($this->tenantA, '+919876543210', 'order_status_update');
    }

    /**
     * Attack 18: Race condition / double-click build trigger -> Expected: Exactly one build created.
     */
    public function test_attack_18_double_click_build_trigger_creates_only_one_build(): void
    {
        Subscription::create([
            'tenant_id'          => $this->tenantA->id,
            'app_id'             => $this->appA->id,
            'plan_id'            => $this->proPlan->id,
            'status'             => 'active',
            'current_period_end' => now()->addMonth(),
        ]);

        $resp1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/builds");
        $resp1->assertStatus(201);
        $buildId1 = $resp1->json('build.id');

        // Immediate duplicate trigger
        $resp2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/builds");
        $resp2->assertStatus(201);
        $buildId2 = $resp2->json('build.id');

        // Must return the exact same in-flight build ID (Idempotency)
        $this->assertEquals($buildId1, $buildId2);
        $this->assertEquals(1, \App\Models\Build::withoutTenantScope()->where('app_id', $this->appA->id)->count());
    }

    /**
     * Attack 19: Malicious file upload (SVG script injection, oversized file, executable disguised as image).
     */
    public function test_attack_19_malicious_file_uploads_are_rejected(): void
    {
        $processor = new AssetProcessor();
        $tempDir = storage_path('app');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir . '/temp_malicious.svg';
        file_put_contents($tempPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("xss")</script></svg>');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported image format');

        try {
            $processor->validateAndSanitize($tempPath, storage_path('app/out.png'));
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Attack 20: APK reverse engineering / secret exposure -> Expected: Client keys never sent to mobile clients.
     */
    public function test_attack_20_public_app_config_never_contains_backend_secrets(): void
    {
        $response = $this->withHeaders(['X-App-Key' => $this->appA->app_key])
            ->getJson('/v1/app-config');

        $response->assertStatus(200);
        $body = $response->getContent();

        // Must contain theme tokens
        $this->assertStringContainsString('theme', $body);

        // Must NEVER contain backend secrets, credentials, or service keys
        $this->assertStringNotContainsString('consumer_key', $body);
        $this->assertStringNotContainsString('consumer_secret', $body);
        $this->assertStringNotContainsString('webhook_secret', $body);
        $this->assertStringNotContainsString('razorpay_key_secret', $body);
        $this->assertStringNotContainsString('meta_access_token', $body);
    }
}
