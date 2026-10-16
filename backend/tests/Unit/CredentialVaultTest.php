<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Resources\ConnectionResource;
use App\Models\App;
use App\Models\Connection;
use App\Models\Tenant;
use App\Services\Security\CredentialVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CredentialVaultTest extends TestCase
{
    use RefreshDatabase;

    private CredentialVault $vault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vault = new CredentialVault();
    }

    public function test_encrypts_and_decrypts_credentials_roundtrip(): void
    {
        $rawCredentials = [
            'consumer_key'    => 'ck_live_998877665544332211aabbcc',
            'consumer_secret' => 'cs_secret_112233445566778899aabbcc',
            'extra_option'    => 'secure_val',
        ];

        $encrypted = $this->vault->encrypt($rawCredentials);

        $this->assertIsString($encrypted);
        $this->assertNotEquals(json_encode($rawCredentials), $encrypted);
        $this->assertStringNotContainsString('cs_secret', $encrypted);
        $this->assertStringNotContainsString('ck_live', $encrypted);

        $decrypted = $this->vault->decrypt($encrypted);
        $this->assertEquals($rawCredentials, $decrypted);
    }

    public function test_masks_consumer_key_appropriately(): void
    {
        $key = 'ck_1234567890abcdef';
        $masked = $this->vault->maskKey($key);

        $this->assertEquals('ck_****cdef', $masked);
        $this->assertStringNotContainsString('1234567890ab', $masked);
    }

    public function test_connection_model_serialization_hides_credentials(): void
    {
        $tenant = Tenant::factory()->create();
        $app = App::withoutTenantScope()->create([
            'tenant_id'    => $tenant->id,
            'name'         => 'Test Store',
            'platform'     => 'woo',
            'package_name' => 'com.store.test',
            'app_key'      => 'app_test_key_123',
            'setup_step'   => 'connect',
            'status'       => 'draft',
        ]);

        $connection = Connection::withoutTenantScope()->create([
            'tenant_id'   => $tenant->id,
            'app_id'      => $app->id,
            'platform'    => 'woo',
            'site_url'    => 'https://example-store.com',
            'credentials' => [
                'consumer_key'    => 'ck_1234567890abcdef',
                'consumer_secret' => 'cs_super_secret_key_9999',
            ],
            'status'      => 'verified',
        ]);

        $serialized = $connection->toArray();

        // Model toArray() must NOT contain credentials key (it is in $hidden)
        $this->assertArrayNotHasKey('credentials', $serialized);
        $this->assertStringNotContainsString('cs_super_secret_key_9999', json_encode($serialized));
        $this->assertStringNotContainsString('ck_1234567890abcdef', json_encode($serialized));
    }

    public function test_connection_resource_never_exposes_raw_credentials_or_secrets(): void
    {
        $tenant = Tenant::factory()->create();
        $app = App::withoutTenantScope()->create([
            'tenant_id'    => $tenant->id,
            'name'         => 'Test Store',
            'platform'     => 'woo',
            'package_name' => 'com.store.res',
            'app_key'      => 'app_test_key_res',
            'setup_step'   => 'connect',
            'status'       => 'draft',
        ]);

        $connection = Connection::withoutTenantScope()->create([
            'tenant_id'   => $tenant->id,
            'app_id'      => $app->id,
            'platform'    => 'woo',
            'site_url'    => 'https://example-store.com',
            'credentials' => [
                'consumer_key'    => 'ck_1234567890abcdef',
                'consumer_secret' => 'cs_ultra_private_secret_999',
            ],
            'status'      => 'verified',
        ]);

        $resource = (new ConnectionResource($connection))->toArray(Request::create('/api/apps/ulid/connection'));

        // Verify keys
        $this->assertArrayNotHasKey('credentials', $resource);
        $this->assertArrayNotHasKey('consumer_secret', $resource);
        $this->assertArrayNotHasKey('secret', $resource);

        // Raw values must NEVER appear
        $json = json_encode($resource);
        $this->assertStringNotContainsString('cs_ultra_private_secret_999', $json);
        $this->assertStringNotContainsString('ck_1234567890abcdef', $json);

        // Only preview masked key is allowed
        $this->assertEquals('ck_****cdef', $resource['consumer_key_preview']);
    }
}
