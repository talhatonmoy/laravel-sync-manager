<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_version_id')->constrained('sync_versions')->cascadeOnDelete();
            $table->string('path');
            $table->string('hash')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status')->default('synced');
            $table->timestamp('modified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['sync_version_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_files');
    }
};
