<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('razorpay_payment_id')->unique(); // idempotency key — no double-credit
            $table->unsignedBigInteger('amount');            // in paise
            $table->string('status', 30);
            $table->json('raw');                             // raw Razorpay payload (audit)
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
