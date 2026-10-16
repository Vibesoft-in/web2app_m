<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();                  // public-facing ID (non-guessable)
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('platform', 20);                  // woo|shopify
            $table->string('package_name')->unique()->nullable(); // com.example.app
            $table->string('app_key', 64)->unique();         // public API key for mobile
            $table->string('setup_step', 30)->default('platform');
            $table->string('status', 30)->default('draft'); // draft|verified|configured|paid|building|live
            $table->string('firebase_project_id')->nullable();
            $table->string('firebase_app_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')
                  ->references('id')->on('tenants')
                  ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index('app_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
