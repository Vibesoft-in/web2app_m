<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id');
            $table->string('title');
            $table->text('body');
            $table->json('audience')->nullable(); // { type: all|segment, filters: [...] }
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('app_id')->references('id')->on('apps')->onDelete('restrict');

            $table->index('tenant_id');
            $table->index(['tenant_id', 'app_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
