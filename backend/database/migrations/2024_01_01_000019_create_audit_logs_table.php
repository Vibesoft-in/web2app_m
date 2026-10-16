<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable(); // nullable for owner actions
            $table->unsignedBigInteger('actor_id')->nullable();  // nullable if actor deleted
            $table->string('action', 100);   // e.g. app.created, impersonation.started
            $table->string('target')->nullable(); // e.g. App:ulid-xxx
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at');

            $table->index('tenant_id');
            $table->index('actor_id');
            $table->index('action');
            $table->index('created_at'); // for 1-year retention cleanup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
