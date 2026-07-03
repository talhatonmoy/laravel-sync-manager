<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_id')->unique();
            $table->foreignId('sync_version_id')->nullable()->constrained('sync_versions')->nullOnDelete();
            $table->string('type');
            $table->string('strategy')->nullable();
            $table->string('target_name')->nullable();
            $table->string('status')->default('queued');
            $table->string('stage')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('message')->nullable();
            $table->json('result_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
