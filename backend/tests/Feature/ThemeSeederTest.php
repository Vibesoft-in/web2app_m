<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\App;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_theme_seeder_creates_3_active_themes_with_valid_defaults(): void
    {
        $this->seed(ThemeSeeder::class);

        $this->assertDatabaseCount('themes', 3);
        $this->assertDatabaseHas('themes', ['key' => 'theme_a', 'name' => 'Modern Retail']);
        $this->assertDatabaseHas('themes', ['key' => 'theme_b', 'name' => 'Luxury Boutique']);
        $this->assertDatabaseHas('themes', ['key' => 'theme_c', 'name' => 'Vibrant Lifestyle']);

        $themeA = Theme::where('key', 'theme_a')->first();
        $this->assertNotNull($themeA);
        $this->assertEquals('#E91E63', $themeA->defaults['colors']['primary']);
    }

    public function test_get_themes_api_returns_seeded_themes(): void
    {
        $this->seed(ThemeSeeder::class);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id'         => $tenant->id,
            'email_verified_at' => now(),
        ]);
        $app = App::withoutTenantScope()->create([
            'tenant_id'    => $tenant->id,
            'name'         => 'Test Store',
            'platform'     => 'woo',
            'package_name' => 'com.store.themes',
            'app_key'      => 'app_themes_key',
            'setup_step'   => 'theme',
            'status'       => 'draft',
        ]);

        $token = $user->createToken('dashboard')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/apps/{$app->ulid}/themes");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'themes')
            ->assertJsonFragment(['key' => 'theme_a'])
            ->assertJsonFragment(['key' => 'theme_b'])
            ->assertJsonFragment(['key' => 'theme_c']);
    }
}
