<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id');
            $table->string('platform', 20);
            $table->string('site_url');
            // Encrypted JSON: { consumer_key, consumer_secret } for Woo
            // or { access_token, storefront_token } for Shopify
            $table->text('credentials');
            $table->string('status', 30)->default('not_started');
            $table->timestamp('last_verified_at')->nullable();
            $table->string('last_error_code', 50)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('app_id')->references('id')->on('apps')->onDelete('cascade');

            $table->index('tenant_id');
            $table->index(['tenant_id', 'app_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
