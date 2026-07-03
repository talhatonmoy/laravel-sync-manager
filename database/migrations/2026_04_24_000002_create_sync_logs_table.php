<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_version_id')->nullable()->constrained('sync_versions')->nullOnDelete();
            $table->string('level')->default('info');
            $table->string('stage')->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
