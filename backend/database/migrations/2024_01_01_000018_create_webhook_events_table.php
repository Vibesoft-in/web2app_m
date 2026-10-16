<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30);           // razorpay|woo|shopify
            $table->string('event_id')->unique();   // provider's event ID (replay block)
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('source');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
