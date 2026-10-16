<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id');
            $table->string('type', 30); // firebase|whatsapp|otp|google
            $table->string('status', 30)->default('pending');
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('app_id')->references('id')->on('apps')->onDelete('cascade');

            $table->index('tenant_id');
            $table->unique(['app_id', 'type']); // one integration per type per app
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
