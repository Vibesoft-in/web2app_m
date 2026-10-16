<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\App;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['name' => 'Store Alpha']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Store Beta']);

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
    }

    public function test_tenant_a_cannot_read_tenant_b_apps(): void
    {
        // Create apps for both tenants without scope
        $appA = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'name'         => 'Alpha App',
            'platform'     => 'woo',
            'package_name' => 'com.alpha.app',
            'app_key'      => 'key_alpha_123',
            'setup_step'   => 'platform',
            'status'       => 'draft',
        ]);

        $appB = App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantB->id,
            'name'         => 'Beta App',
            'platform'     => 'woo',
            'package_name' => 'com.beta.app',
            'app_key'      => 'key_beta_123',
            'setup_step'   => 'platform',
            'status'       => 'draft',
        ]);

        // Authenticate as User A
        $this->actingAs($this->userA);

        // Tenant A only sees their own apps
        $visibleApps = App::all();
        $this->assertCount(1, $visibleApps);
        $this->assertTrue($visibleApps->contains('id', $appA->id));
        $this->assertFalse($visibleApps->contains('id', $appB->id));

        // Attempting to find Tenant B's app by ID returns null due to global scope
        $this->assertNull(App::find($appB->id));

        // Attempting to query by ULID returns null
        $this->assertNull(App::where('ulid', $appB->ulid)->first());
    }

    public function test_model_creation_automatically_assigns_authenticated_tenant_id(): void
    {
        $this->actingAs($this->userA);

        // Create an app without specifying tenant_id
        $app = App::create([
            'name'         => 'Auto Tenant App',
            'platform'     => 'woo',
            'package_name' => 'com.alpha.auto',
            'app_key'      => 'key_alpha_auto',
            'setup_step'   => 'platform',
            'status'       => 'draft',
        ]);

        $this->assertEquals($this->tenantA->id, $app->tenant_id);
    }

    public function test_authenticated_user_accessing_user_endpoint_receives_tenant_context(): void
    {
        $token = $this->userA->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'id'        => $this->userA->id,
                'email'     => $this->userA->email,
                'tenant_id' => $this->tenantA->id,
            ]);
    }

    public function test_without_tenant_scope_allows_system_jobs_to_query_across_tenants(): void
    {
        App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantA->id,
            'name'         => 'Alpha App',
            'platform'     => 'woo',
            'package_name' => 'com.alpha.sys',
            'app_key'      => 'key_alpha_sys',
            'setup_step'   => 'platform',
            'status'       => 'draft',
        ]);

        App::withoutTenantScope()->create([
            'tenant_id'    => $this->tenantB->id,
            'name'         => 'Beta App',
            'platform'     => 'woo',
            'package_name' => 'com.beta.sys',
            'app_key'      => 'key_beta_sys',
            'setup_step'   => 'platform',
            'status'       => 'draft',
        ]);

        $this->actingAs($this->userA);

        // Scoped query has 1
        $this->assertEquals(1, App::count());

        // withoutTenantScope has both
        $this->assertEquals(2, App::withoutTenantScope()->count());
    }
}
