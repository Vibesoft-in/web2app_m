<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendCampaignJob;
use App\Jobs\SendOrderStatusPushJob;
use App\Models\App;
use App\Models\AppUser;
use App\Models\Connection;
use App\Models\NotificationCampaign;
use App\Models\Plan;
use App\Models\PushDevice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UsageEvent;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Billing\EntitlementService;
use App\Services\Notifications\DeepLinkValidator;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationAndWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private App $appA;
    private App $appB;
    private Plan $starterPlan;
    private Plan $proPlan;
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.webhook_verify_token' => 'appify_whatsapp_verify_token']);

        // Set up Tenant A and Tenant B
        $this->tenantA = Tenant::factory()->create(['name' => 'Store Alpha']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Store Beta']);

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

        $this->starterPlan = Plan::create([
            'name'          => 'Starter',
            'price_monthly' => 99900,
            'features'      => ['whatsapp' => false],
            'limits'        => ['push_per_month' => 5, 'builds_per_month' => 3],
            'is_active'     => true,
        ]);

        $this->proPlan = Plan::create([
            'name'          => 'Pro',
            'price_monthly' => 199900,
            'features'      => ['whatsapp' => true],
            'limits'        => ['push_per_month' => 1000, 'builds_per_month' => 10],
            'is_active'     => true,
        ]);

        Subscription::create([
            'tenant_id'            => $this->tenantA->id,
            'plan_id'              => $this->starterPlan->id,
            'status'               => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end'   => now()->endOfMonth(),
        ]);

        Subscription::create([
            'tenant_id'            => $this->tenantB->id,
            'plan_id'              => $this->proPlan->id,
            'status'               => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end'   => now()->endOfMonth(),
        ]);

        $this->appA = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'name'         => 'Alpha Store App',
            'platform'     => 'woo',
            'package_name' => 'com.alpha.app',
            'app_key'      => 'app_key_alpha_111',
            'setup_step'   => 'payment',
            'status'       => 'active',
        ]);

        $this->appB = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantB->id,
            'name'         => 'Beta Store App',
            'platform'     => 'woo',
            'package_name' => 'com.beta.app',
            'app_key'      => 'app_key_beta_222',
            'setup_step'   => 'payment',
            'status'       => 'active',
        ]);

        $this->tokenA = $this->userA->createToken('dashboard')->plainTextToken;
    }

    /**
     * Test 1: Device registration and unregistration flow (/v1/devices).
     */
    public function test_device_registration_and_unregistration_flow(): void
    {
        $customer = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenantA->id,
            'app_id'            => $this->appA->id,
            'phone'             => '+15551234567',
            'store_customer_id' => 'cust_001',
        ]);

        $customerToken = $customer->createToken('session', ["app:{$this->appA->id}"])->plainTextToken;

        // 1. Register device
        $regResponse = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$customerToken}",
        ])->postJson('/v1/devices', [
            'fcm_token' => 'fcm_token_device_abc',
            'platform'  => 'android',
        ]);

        $regResponse->assertStatus(200);
        $regResponse->assertJson(['message' => 'Device registered for notifications.']);

        $this->assertDatabaseHas('push_devices', [
            'tenant_id'   => $this->tenantA->id,
            'app_id'      => $this->appA->id,
            'app_user_id' => $customer->id,
            'fcm_token'   => 'fcm_token_device_abc',
            'platform'    => 'android',
        ]);

        // 2. Unregister device
        $unregResponse = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$customerToken}",
        ])->deleteJson('/v1/devices', [
            'fcm_token' => 'fcm_token_device_abc',
        ]);

        $unregResponse->assertStatus(200);
        $unregResponse->assertJson(['message' => 'Device unregistered.']);

        $this->assertDatabaseMissing('push_devices', [
            'fcm_token' => 'fcm_token_device_abc',
        ]);
    }

    /**
     * Test 2: Tenant isolation of devices.
     */
    public function test_tenant_isolation_of_devices(): void
    {
        $deviceA = PushDevice::withoutTenantScope()->create([
            'tenant_id'   => $this->tenantA->id,
            'app_id'      => $this->appA->id,
            'fcm_token'   => 'token_tenant_a',
            'platform'    => 'android',
        ]);

        $deviceB = PushDevice::withoutTenantScope()->create([
            'tenant_id'   => $this->tenantB->id,
            'app_id'      => $this->appB->id,
            'fcm_token'   => 'token_tenant_b',
            'platform'    => 'ios',
        ]);

        // Querying for App A devices under tenant A scope
        $appADevices = PushDevice::withoutTenantScope()
            ->where('tenant_id', $this->tenantA->id)
            ->where('app_id', $this->appA->id)
            ->pluck('fcm_token')
            ->toArray();

        $this->assertContains('token_tenant_a', $appADevices);
        $this->assertNotContains('token_tenant_b', $appADevices);
    }

    /**
     * Test 3: Deep link validator allowlist (product:ID, order:ID, category:ID).
     */
    public function test_deep_link_validator_allows_valid_and_rejects_malicious(): void
    {
        $validator = app(DeepLinkValidator::class);

        // Valid formats
        $this->assertTrue($validator->isValid('product:123'));
        $this->assertTrue($validator->isValid('product:hoodie-blue-99'));
        $this->assertTrue($validator->isValid('order:456'));
        $this->assertTrue($validator->isValid('order:ord_abc_123'));
        $this->assertTrue($validator->isValid('category:shoes'));
        $this->assertTrue($validator->isValid('category:winter-collection'));
        $this->assertTrue($validator->isValid(null)); // Optional deep link

        // Malicious / disallowed formats
        $this->assertFalse($validator->isValid('https://malicious.com'));
        $this->assertFalse($validator->isValid('javascript:alert(1)'));
        $this->assertFalse($validator->isValid('file:///etc/passwd'));
        $this->assertFalse($validator->isValid('product:')); // missing ID
        $this->assertFalse($validator->isValid('unknown:123'));
        $this->assertFalse($validator->isValid('/product/123'));
        $this->assertFalse($validator->isValid('product:123;drop table users;'));
    }

    /**
     * Test 4: FCM Service invalid token cleanup.
     */
    public function test_fcm_service_cleans_up_invalid_tokens(): void
    {
        PushDevice::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'app_id'    => $this->appA->id,
            'fcm_token' => 'invalid_stale_token',
            'platform'  => 'android',
        ]);

        $this->assertDatabaseHas('push_devices', ['fcm_token' => 'invalid_stale_token']);

        /** @var FcmService $fcmService */
        $fcmService = app(FcmService::class);
        $sent = $fcmService->sendToToken('invalid_stale_token', 'Test', 'Body');

        $this->assertFalse($sent);
        // Assert stale token was automatically pruned from database
        $this->assertDatabaseMissing('push_devices', ['fcm_token' => 'invalid_stale_token']);
    }

    /**
     * Test 5: Campaign creation and dispatch with tenant isolation.
     */
    public function test_campaign_creation_and_dispatch_with_tenant_isolation(): void
    {
        // Device on App A
        PushDevice::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'app_id'    => $this->appA->id,
            'fcm_token' => 'token_for_app_a',
            'platform'  => 'android',
        ]);

        // Device on App B
        PushDevice::withoutTenantScope()->create([
            'tenant_id' => $this->tenantB->id,
            'app_id'    => $this->appB->id,
            'fcm_token' => 'token_for_app_b',
            'platform'  => 'android',
        ]);

        // 1. Create campaign via Dashboard API
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/notifications/campaigns", [
                'title'     => 'Flash Sale 50% Off!',
                'body'      => 'Get your favorite sneakers at half price now.',
                'deep_link' => 'product:sneakers-50',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Campaign queued for delivery.',
        ]);

        $campaignId = $response->json('campaign.id');
        $this->assertNotNull($campaignId);

        // 2. Execute SendCampaignJob
        $campaign = NotificationCampaign::withoutTenantScope()->find($campaignId);
        $job = new SendCampaignJob($campaignId);
        $job->handle(app(FcmService::class));

        $campaign->refresh();
        $this->assertEquals('completed', $campaign->status);

        // Usage event created for Tenant A, not Tenant B
        $this->assertDatabaseHas('usage_events', [
            'tenant_id' => $this->tenantA->id,
            'app_id'    => $this->appA->id,
            'type'      => 'push_send',
            'qty'       => 1,
        ]);

        $this->assertDatabaseMissing('usage_events', [
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /**
     * Test 6: Cross-tenant isolation - Tenant A cannot post or view campaigns on Tenant B's app.
     */
    public function test_cross_tenant_campaign_access_is_forbidden(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appB->ulid}/notifications/campaigns", [
                'title' => 'Malicious Campaign',
                'body'  => 'Attempting cross-tenant send',
            ]);

        // App B does not belong to Tenant A, resulting in 404 (tenant global scope)
        $response->assertStatus(404);
    }

    /**
     * Test 7: Campaign scheduled for future sets scheduled status.
     */
    public function test_campaign_scheduled_for_future(): void
    {
        Queue::fake();

        $futureDate = now()->addDays(3)->toIso8601String();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/notifications/campaigns", [
                'title'        => 'Scheduled Weekend Sale',
                'body'         => 'Sale begins Saturday at midnight.',
                'scheduled_at' => $futureDate,
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message'  => 'Campaign scheduled successfully.',
            'campaign' => [
                'status' => 'scheduled',
            ],
        ]);

        Queue::assertNotPushed(SendCampaignJob::class);
    }

    /**
     * Test 8: Campaign rejected when deep link is invalid.
     */
    public function test_campaign_rejected_when_deep_link_invalid(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/notifications/campaigns", [
                'title'     => 'Phishing Alert',
                'body'      => 'Visit external site',
                'deep_link' => 'https://malicious-phishing-url.com',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => [
                'code' => 'INVALID_DEEP_LINK',
            ],
        ]);
    }

    /**
     * Test 9: Campaign creation gated by entitlement limit (push_per_month).
     */
    public function test_campaign_creation_gated_by_entitlement_limit(): void
    {
        // Starter plan limit is 5. Record 5 usage events to exhaust quota.
        UsageEvent::withoutTenantScope()->create([
            'tenant_id'  => $this->tenantA->id,
            'app_id'     => $this->appA->id,
            'type'       => 'push_send',
            'qty'        => 5,
            'cost'       => 0.0,
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/notifications/campaigns", [
                'title' => 'Exceeded Limit Campaign',
                'body'  => 'This should be blocked',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'PUSH_LIMIT_EXCEEDED',
            ],
        ]);
    }

    /**
     * Test 10: Store webhook triggers order status push notification.
     */
    public function test_store_webhook_triggers_order_status_push(): void
    {
        Queue::fake();

        $secret = 'test_webhook_secret_key';
        Connection::withoutTenantScope()->create([
            'tenant_id'              => $this->tenantA->id,
            'app_id'                 => $this->appA->id,
            'site_url'               => 'https://alpha-store.com',
            'consumer_key_encrypted' => 'enc_ck',
            'consumer_secret_encrypted' => 'enc_cs',
            'credentials'            => ['webhook_secret' => $secret],
        ]);

        $payload = [
            'id'          => 8899,
            'customer_id' => 'cust_101',
            'status'      => 'completed',
        ];
        $rawPayload = json_encode($payload);
        $signature = base64_encode(hash_hmac('sha256', (string) $rawPayload, $secret, true));

        $response = $this->withHeaders([
            'X-WC-Webhook-Signature'   => $signature,
            'X-WC-Webhook-Topic'       => 'order.updated',
            'X-WC-Webhook-Delivery-ID' => 'delivery_order_8899',
            'Content-Type'             => 'application/json',
        ])->postJson("/webhooks/woo/{$this->appA->ulid}", $payload);

        $response->assertStatus(200);

        Queue::assertPushed(SendOrderStatusPushJob::class, function ($job) {
            return $job->appId === $this->appA->id
                && $job->orderId === '8899'
                && $job->storeCustomerId === 'cust_101'
                && $job->status === 'completed';
        });
    }

    /**
     * Test 11: WhatsApp service is gated by entitlement (Starter plan lacks 'whatsapp').
     */
    public function test_whatsapp_service_gated_by_entitlement(): void
    {
        /** @var WhatsAppService $whatsAppService */
        $whatsAppService = app(WhatsAppService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WhatsApp messaging is not included in your current plan');

        $whatsAppService->sendTemplateMessage(
            $this->tenantA,
            '+919876543210',
            'order_status_update'
        );
    }

    /**
     * Test 12: WhatsApp service debits wallet before sending for entitled tenant.
     */
    public function test_whatsapp_service_debits_wallet_before_sending(): void
    {
        /** @var WhatsAppService $whatsAppService */
        $whatsAppService = app(WhatsAppService::class);

        $this->assertEquals(0, UsageEvent::withoutTenantScope()->where('tenant_id', $this->tenantB->id)->count());

        $result = $whatsAppService->sendTemplateMessage(
            $this->tenantB,
            '+919876543210',
            'order_status_update'
        );

        $this->assertNotEmpty($result['message_id']);
        $this->assertEquals('sent', $result['status']);
        $this->assertEquals(0.80, $result['cost']);

        // Assert wallet debit recorded
        $this->assertDatabaseHas('usage_events', [
            'tenant_id' => $this->tenantB->id,
            'type'      => 'whatsapp_message',
            'qty'       => 1,
            'cost'      => 0.80,
        ]);
    }

    /**
     * Test 13: WhatsApp test message endpoint via Dashboard API.
     */
    public function test_whatsapp_test_message_endpoint(): void
    {
        $tokenB = $this->userB->createToken('dashboard')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson("/api/apps/{$this->appB->ulid}/integrations/whatsapp/test", [
                'phone' => '+919876543210',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'WhatsApp test message sent successfully.',
            'status'  => 'sent',
            'cost'    => 0.80,
        ]);
        $this->assertNotEmpty($response->json('message_id'));
    }

    /**
     * Test 14: WhatsApp webhook verification challenge (GET).
     */
    public function test_whatsapp_webhook_verification_challenge(): void
    {
        // Valid verify token
        $response = $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.challenge=test_challenge_abc_123&hub.verify_token=appify_whatsapp_verify_token');

        $response->assertStatus(200);
        $this->assertEquals('test_challenge_abc_123', $response->getContent());

        // Invalid verify token
        $invalidResponse = $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.challenge=test_challenge_abc_123&hub.verify_token=wrong_token');
        $invalidResponse->assertStatus(403);
    }

    /**
     * Test 15: WhatsApp delivery webhook records delivery status updates.
     */
    public function test_whatsapp_delivery_webhook_records_status_updates(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry'  => [
                [
                    'id'      => 'wamid_biz_123',
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'statuses'          => [
                                    [
                                        'id'           => 'wamid.HBgL123456',
                                        'status'       => 'delivered',
                                        'recipient_id' => '919876543210',
                                        'timestamp'    => '1700000000',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/webhooks/whatsapp', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'acknowledged',
        ]);

        $this->assertDatabaseHas('webhook_events', [
            'source'   => 'whatsapp',
            'event_id' => 'wa_status_wamid.HBgL123456_delivered',
        ]);
    }

    /**
     * Test 16: Cross-tenant device unregistration is strictly blocked.
     */
    public function test_cross_tenant_device_unregistration_is_prevented(): void
    {
        $deviceB = PushDevice::withoutTenantScope()->create([
            'tenant_id'   => $this->tenantB->id,
            'app_id'      => $this->appB->id,
            'fcm_token'   => 'token_tenant_b_protected',
            'platform'    => 'android',
        ]);

        $customerA = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenantA->id,
            'app_id'            => $this->appA->id,
            'phone'             => '+15559998888',
            'store_customer_id' => 'cust_002',
        ]);

        $customerTokenA = $customerA->createToken('session', ["app:{$this->appA->id}"])->plainTextToken;

        // Customer A attempts to delete Tenant B's token via App A's endpoint
        $response = $this->withHeaders([
            'X-App-Key'     => $this->appA->app_key,
            'Authorization' => "Bearer {$customerTokenA}",
        ])->deleteJson('/v1/devices', [
            'fcm_token' => 'token_tenant_b_protected',
        ]);

        $response->assertStatus(200);

        // Tenant B's device token MUST remain untouched in the database
        $this->assertDatabaseHas('push_devices', [
            'tenant_id' => $this->tenantB->id,
            'app_id'    => $this->appB->id,
            'fcm_token' => 'token_tenant_b_protected',
        ]);
    }

    /**
     * Test 17: Campaign creation rejects past scheduled date and sanitizes HTML/XSS.
     */
    public function test_campaign_creation_rejects_past_date_and_sanitizes_html(): void
    {
        // Past date is rejected (422)
        $pastResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/notifications/campaigns", [
                'title'        => 'Past Flash Sale',
                'body'         => 'Cannot schedule in the past',
                'scheduled_at' => now()->subDays(2)->toIso8601String(),
            ]);

        $pastResponse->assertStatus(422);

        // HTML/XSS is stripped
        $xssResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/apps/{$this->appA->ulid}/notifications/campaigns", [
                'title' => '<script>alert("xss")</script>Secure Title',
                'body'  => '<b>Bold Notice</b> with <iframe src="evil.com"></iframe> content',
            ]);

        $xssResponse->assertStatus(201);
        $campaign = NotificationCampaign::withoutTenantScope()->find($xssResponse->json('campaign.id'));
        $this->assertNotNull($campaign);
        $this->assertEquals('alert("xss")Secure Title', $campaign->title);
        $this->assertEquals('Bold Notice with  content', $campaign->body);
    }

    /**
     * Test 18: SendCampaignJob is atomic and prevents duplicate sends across concurrent workers.
     */
    public function test_send_campaign_job_prevents_duplicate_runs(): void
    {
        $campaign = NotificationCampaign::withoutTenantScope()->create([
            'tenant_id' => $this->tenantA->id,
            'app_id'    => $this->appA->id,
            'title'     => 'Atomic Sale',
            'body'      => 'Checking atomicity',
            'status'    => 'processing', // Already processing by another worker
        ]);

        $initialEvents = UsageEvent::withoutTenantScope()->count();

        $job = new SendCampaignJob($campaign->id);
        $job->handle(app(FcmService::class));

        // No new usage events generated since job exited early
        $this->assertEquals($initialEvents, UsageEvent::withoutTenantScope()->count());
    }

    /**
     * Test 19: WhatsApp delivery webhook verifies HMAC signature.
     */
    public function test_whatsapp_webhook_signature_verification(): void
    {
        config(['services.whatsapp.app_secret' => 'meta_test_secret_key']);

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry'  => [],
        ]);

        // 1. Invalid signature is rejected (401)
        $invalidResp = $this->withHeaders([
            'X-Hub-Signature-256' => 'sha256=invalid_forged_hash',
            'Content-Type'        => 'application/json',
        ])->call('POST', '/webhooks/whatsapp', [], [], [], [], (string) $payload);

        $invalidResp->assertStatus(401);
        $invalidResp->assertJson(['error' => ['code' => 'INVALID_SIGNATURE']]);

        // 2. Valid signature is accepted (200)
        $validHash = 'sha256=' . hash_hmac('sha256', (string) $payload, 'meta_test_secret_key');
        $validResp = $this->withHeaders([
            'X-Hub-Signature-256' => $validHash,
            'Content-Type'        => 'application/json',
        ])->call('POST', '/webhooks/whatsapp', [], [], [], [], (string) $payload);

        $validResp->assertStatus(200);
        $validResp->assertJson(['status' => 'acknowledged']);
    }

    /**
     * Test 20: WhatsAppService rejects malformed template names.
     */
    public function test_whatsapp_service_rejects_malformed_template_names(): void
    {
        /** @var WhatsAppService $whatsAppService */
        $whatsAppService = app(WhatsAppService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid template name');

        $whatsAppService->sendTemplateMessage(
            $this->tenantB,
            '+919876543210',
            'Illegal Template Name!'
        );
    }
}
