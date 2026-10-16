<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id');
            $table->unsignedBigInteger('app_user_id');
            $table->string('fcm_token', 200);
            $table->string('platform', 10)->default('android'); // android|ios
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('app_id')->references('id')->on('apps')->onDelete('restrict');
            $table->foreign('app_user_id')->references('id')->on('app_users')->onDelete('cascade');

            $table->index('tenant_id');
            $table->index(['tenant_id', 'app_id']);
            $table->index('fcm_token');
            // FCM token is unique per user+app (replace on re-register)
            $table->unique(['app_id', 'app_user_id', 'fcm_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
