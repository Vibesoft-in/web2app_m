<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('status', 30)->default('pending');
            $table->string('razorpay_sub_id')->nullable()->unique();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('app_id')->references('id')->on('apps')->onDelete('restrict');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('restrict');

            $table->index('tenant_id');
            $table->index(['tenant_id', 'app_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
