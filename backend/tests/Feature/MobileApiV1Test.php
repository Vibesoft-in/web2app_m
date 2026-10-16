<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppUser;
use App\Models\Connection;
use App\Models\Tenant;
use App\Services\Connectors\ConnectorFactory;
use App\Services\Connectors\ConnectorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class MobileApiV1Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private App $appX;
    private App $appY;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('otp_req:*');

        $this->tenant = Tenant::factory()->create(['name' => 'Demo Tenant']);

        $this->appX = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Store App X',
            'platform'     => 'woo',
            'package_name' => 'com.store.appx',
            'app_key'      => 'app_key_x_12345',
            'setup_step'   => 'build',
            'status'       => 'active',
        ]);

        $this->appY = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenant->id,
            'name'         => 'Store App Y',
            'platform'     => 'woo',
            'package_name' => 'com.store.appy',
            'app_key'      => 'app_key_y_67890',
            'setup_step'   => 'build',
            'status'       => 'active',
        ]);

        Connection::withoutTenantScope()->create([
            'tenant_id'             => $this->tenant->id,
            'app_id'                => $this->appX->id,
            'site_url'              => 'https://mystore-x.com',
            'consumer_key_encrypted'=> 'enc_ck',
            'consumer_secret_encrypted' => 'enc_cs',
            'status'                => 'verified',
            'verified_at'           => now(),
        ]);
    }

    /**
     * Test: Token for App X is strictly rejected on App Y (403 FORBIDDEN_APP_MISMATCH).
     */
    public function test_token_for_app_x_rejected_on_app_y(): void
    {
        $userX = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenant->id,
            'app_id'            => $this->appX->id,
            'phone'             => '+15550001111',
            'store_customer_id' => 'cust_x_1',
            'name'              => 'Customer X',
        ]);

        $tokenX = $userX->createToken('session', ["app:{$this->appX->id}"])->plainTextToken;

        // Making request to App Y using App X's token
        $response = $this->withHeaders([
            'X-App-Key'     => $this->appY->app_key,
            'Authorization' => "Bearer {$tokenX}",
        ])->getJson('/v1/orders');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => [
                'code' => 'FORBIDDEN_APP_MISMATCH',
            ],
        ]);
    }

    /**
     * Test: User A cannot read User B's orders (IDOR protection & customer filtering).
     */
    public function test_user_a_cannot_read_user_b_orders(): void
    {
        $userA = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenant->id,
            'app_id'            => $this->appX->id,
            'phone'             => '+15550002222',
            'store_customer_id' => '101',
            'name'              => 'Alice A',
        ]);

        $userB = AppUser::withoutTenantScope()->create([
            'tenant_id'         => $this->tenant->id,
            'app_id'            => $this->appX->id,
            'phone'             => '+15550003333',
            'store_customer_id' => '202',
            'name'              => 'Bob B',
        ]);

        $tokenA = $userA->createToken('session', ["app:{$this->appX->id}"])->plainTextToken;

        // Mock connector to return customer-filtered orders
        $mockConnector = Mockery::mock(ConnectorInterface::class);
        $mockConnector->shouldReceive('getOrders')
            ->with('101')
            ->andReturn([
                ['id' => '9001', 'order_number' => 'ORD-9001', 'total' => '45.00', 'status' => 'completed'],
            ]);

        // When User A requests order 9002 (which belongs to Bob B), connector returns null
        $mockConnector->shouldReceive('getOrder')
            ->with('9002', '101')
            ->andReturn(null);

        $mockFactory = Mockery::mock(ConnectorFactory::class);
        $mockFactory->shouldReceive('make')->andReturn($mockConnector);
        $this->app->instance(ConnectorFactory::class, $mockFactory);

        // 1. User A lists orders -> receives only their own
        $listResponse = $this->withHeaders([
            'X-App-Key'     => $this->appX->app_key,
            'Authorization' => "Bearer {$tokenA}",
        ])->getJson('/v1/orders');

        $listResponse->assertOk();
        $this->assertCount(1, $listResponse->json('data'));
        $this->assertEquals('9001', $listResponse->json('data.0.id'));

        // 2. User A attempts to view Bob's order #9002 directly -> 404 NOT_FOUND
        $singleResponse = $this->withHeaders([
            'X-App-Key'     => $this->appX->app_key,
            'Authorization' => "Bearer {$tokenA}",
        ])->getJson('/v1/orders/9002');

        $singleResponse->assertStatus(404);
        $singleResponse->assertJson([
            'error' => [
                'code' => 'NOT_FOUND',
            ],
        ]);
    }

    /**
     * Test: OTP abuse limits (resend cooldown, 5 attempts max, 3 per 10min rate limit).
     */
    public function test_otp_abuse_limits_and_verification_rules(): void
    {
        $phone = '+15551234567';

        // 1. Initial valid OTP request
        $req1 = $this->withHeaders(['X-App-Key' => $this->appX->app_key])
            ->postJson('/v1/auth/otp/request', ['phone' => $phone]);

        $req1->assertOk();
        $req1->assertJsonStructure(['message', 'expires_in', 'resend_in']);

        // 2. Immediate second request within 30s triggers resend cooldown
        $req2 = $this->withHeaders(['X-App-Key' => $this->appX->app_key])
            ->postJson('/v1/auth/otp/request', ['phone' => $phone]);

        $req2->assertStatus(429);
        $req2->assertJson(['error' => ['code' => 'OTP_RESEND_COOLDOWN']]);

        // Fast-forward cooldown
        Cache::forget("otp_cooldown:{$this->appX->id}:{$phone}");

        // 3. Verify incorrect OTP increments attempt counter and rejects
        $verifyBad1 = $this->withHeaders(['X-App-Key' => $this->appX->app_key])
            ->postJson('/v1/auth/otp/verify', ['phone' => $phone, 'otp' => '000000']);
        $verifyBad1->assertStatus(422);
        $verifyBad1->assertJson(['error' => ['code' => 'OTP_INVALID', 'attempts_left' => 4]]);

        // Fail 4 more times to reach 5 attempts
        for ($i = 0; $i < 4; $i++) {
            $this->withHeaders(['X-App-Key' => $this->appX->app_key])
                ->postJson('/v1/auth/otp/verify', ['phone' => $phone, 'otp' => '000000']);
        }

        // 6th attempt must be rejected with OTP_MAX_ATTEMPTS_EXCEEDED (or expired)
        $verifyBad6 = $this->withHeaders(['X-App-Key' => $this->appX->app_key])
            ->postJson('/v1/auth/otp/verify', ['phone' => $phone, 'otp' => '123456']);
        $this->assertContains($verifyBad6->status(), [422, 429]);
    }

    /**
     * Test: Successful OTP verification creates customer link and issues app-scoped token.
     */
    public function test_successful_otp_creates_user_and_scopes_token(): void
    {
        $phone = '+15559876543';

        // Request OTP
        $this->withHeaders(['X-App-Key' => $this->appX->app_key])
            ->postJson('/v1/auth/otp/request', ['phone' => $phone])
            ->assertOk();

        // In testing environment, default generated OTP is 123456
        $verifyRes = $this->withHeaders(['X-App-Key' => $this->appX->app_key])
            ->postJson('/v1/auth/otp/verify', ['phone' => $phone, 'otp' => '123456']);

        $verifyRes->assertOk();
        $verifyRes->assertJsonStructure([
            'access_token',
            'token_type',
            'user' => ['id', 'phone'],
        ]);

        $token = $verifyRes->json('access_token');
        $this->assertNotEmpty($token);

        // Verify newly created user has store_customer_id assigned
        $appUser = AppUser::withoutTenantScope()->where('phone', $phone)->first();
        $this->assertNotNull($appUser);
        $this->assertNotEmpty($appUser->store_customer_id);

        // Verify account deletion removes tokens and user record
        $deleteRes = $this->withHeaders([
            'X-App-Key'     => $this->appX->app_key,
            'Authorization' => "Bearer {$token}",
        ])->deleteJson('/v1/account');

        $deleteRes->assertOk();
        $this->assertNull(AppUser::withoutTenantScope()->where('phone', $phone)->first());
    }
}
