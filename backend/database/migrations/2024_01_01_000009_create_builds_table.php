<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('app_id');
            $table->unsignedInteger('config_version');  // pinned at build time
            $table->string('status', 30)->default('queued');
            $table->string('logs_path')->nullable();       // private storage path
            $table->string('artifact_path')->nullable();   // private storage path
            $table->unsignedInteger('version_code')->nullable();
            $table->string('version_name', 20)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
            $table->foreign('app_id')->references('id')->on('apps')->onDelete('restrict');

            $table->index('tenant_id');
            $table->index(['app_id', 'status']);
            $table->index('status'); // for stuck-build timeout scheduler
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builds');
    }
};
