<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keystores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id')->unique(); // one keystore per app
            $table->text('path');       // encrypted storage path to JKS file
            $table->string('alias');
            $table->text('passwords');  // encrypted JSON { store_password, key_password }
            $table->string('sha1', 45)->nullable();
            $table->string('sha256', 95)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('app_id')->references('id')->on('apps')->onDelete('restrict');

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keystores');
    }
};
