<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version_id')->unique();
            $table->string('operation')->default('sync');
            $table->string('direction')->default('outgoing');
            $table->string('status')->default('queued');
            $table->string('source_app')->nullable();
            $table->string('target_name')->nullable();
            $table->string('archive_path')->nullable();
            $table->string('manifest_path')->nullable();
            $table->json('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_versions');
    }
};
