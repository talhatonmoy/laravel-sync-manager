<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_manifests', function (Blueprint $table): void {
            $table->id();
            $table->string('manifest_id')->unique();
            $table->string('target_name')->nullable();
            $table->string('direction')->default('outgoing');
            $table->string('parent_manifest_id')->nullable();
            $table->foreignId('sync_version_id')->nullable()->constrained('sync_versions')->nullOnDelete();
            $table->string('signature')->nullable();
            $table->json('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['target_name', 'direction', 'created_at']);
        });

        Schema::create('sync_manifest_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_manifest_id')->constrained('sync_manifests')->cascadeOnDelete();
            $table->string('path');
            $table->string('hash');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status')->default('modify');
            $table->timestamp('modified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['sync_manifest_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_manifest_files');
        Schema::dropIfExists('sync_manifests');
    }
};
