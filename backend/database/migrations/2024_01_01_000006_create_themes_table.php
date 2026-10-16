<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique(); // theme_a, theme_b, theme_c
            $table->string('name');
            $table->string('preview_url')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->json('defaults');   // default color/font/radius tokens
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
