<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminAndPlanSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Pricing Plans
        Plan::updateOrCreate(
            ['name' => 'Starter'],
            [
                'price_monthly' => 0, // ₹0/mo
                'features'      => [
                    'apk_download'  => true,
                    'aab_download'  => false,
                    'ipa_download'  => false,
                    'admin_panel'   => false,
                    'wishlist'      => true,
                    'otp_login'     => true,
                    'notifications' => true,
                    'reviews'       => false,
                    'whatsapp'      => false,
                ],
                'limits'        => [
                    'builds_per_month' => 3,
                    'max_apps'         => 1,
                ],
                'is_active'     => true,
            ]
        );

        Plan::updateOrCreate(
            ['name' => 'Pro Growth'],
            [
                'price_monthly' => 199900, // ₹1,999/mo
                'features'      => [
                    'apk_download'  => true,
                    'aab_download'  => true,
                    'ipa_download'  => true,
                    'admin_panel'   => false,
                    'wishlist'      => true,
                    'otp_login'     => true,
                    'notifications' => true,
                    'reviews'       => true,
                    'whatsapp'      => false,
                ],
                'limits'        => [
                    'builds_per_month' => 10,
                    'max_apps'         => 3,
                ],
                'is_active'     => true,
            ]
        );

        Plan::updateOrCreate(
            ['name' => 'Enterprise'],
            [
                'price_monthly' => 499900, // ₹4,999/mo
                'features'      => [
                    'apk_download'  => true,
                    'aab_download'  => true,
                    'ipa_download'  => true,
                    'admin_panel'   => true,
                    'wishlist'      => true,
                    'otp_login'     => true,
                    'notifications' => true,
                    'reviews'       => true,
                    'whatsapp'      => true,
                ],
                'limits'        => [
                    'builds_per_month' => 50,
                    'max_apps'         => 10,
                ],
                'is_active'     => true,
            ]
        );

        // 2. Ensure default HQ tenant
        $tenant = Tenant::first();
        if (! $tenant) {
            $tenant = Tenant::create([
                'ulid'   => (string) Str::ulid(),
                'name'   => 'Appify HQ',
                'status' => 'active',
            ]);
        }

        // 3. Super Admin User
        User::updateOrCreate(
            ['email' => 'admin@webintoapp.com'],
            [
                'tenant_id'         => $tenant->id,
                'name'              => 'Super Admin',
                'password'          => 'Admin@12345',
                'role'              => UserRole::Owner,
                'email_verified_at' => now(),
            ]
        );
    }
}
