<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id')->nullable(); // nullable for tenant-level events
            $table->string('type', 50); // push_send, build, whatsapp_message, etc.
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('cost', 10, 4)->default(0);
            $table->timestamp('created_at');

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');

            $table->index('tenant_id');
            $table->index(['tenant_id', 'type']);
            $table->index('created_at'); // for monthly rollup scheduler
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
