<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('store_customer_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('app_id')->references('id')->on('apps')->onDelete('restrict');

            // Security: unique phone per tenant+app prevents duplicate accounts
            $table->unique(['tenant_id', 'app_id', 'phone']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'app_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_users');
    }
};
