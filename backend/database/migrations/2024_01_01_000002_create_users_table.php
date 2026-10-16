<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->string('role', 20)->default('owner'); // owner|editor|viewer
            $table->string('two_factor_secret')->nullable(); // stored encrypted via cast
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')
                  ->references('id')->on('tenants')
                  ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index(['tenant_id', 'role']);
        });

        // Now that users table exists, add FK on tenants.owner_user_id (MySQL production)
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            Schema::table('tenants', function (Blueprint $table) {
                $table->foreign('owner_user_id')
                      ->references('id')->on('users')
                      ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropForeign(['owner_user_id']);
            });
        }
        Schema::dropIfExists('users');
    }
};
