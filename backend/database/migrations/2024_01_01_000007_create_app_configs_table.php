<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('state', 20)->default('draft'); // draft|published
            $table->json('config');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('app_id')->references('id')->on('apps')->onDelete('cascade');

            $table->index('tenant_id');
            $table->index(['app_id', 'state']);
            $table->index(['app_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_configs');
    }
};
