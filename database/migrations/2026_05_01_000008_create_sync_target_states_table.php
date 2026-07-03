<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_target_states', function (Blueprint $table): void {
            $table->id();
            $table->string('target_name');
            $table->string('path');
            $table->string('hash');
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->string('manifest_id')->nullable();
            $table->timestamps();
            $table->unique(['target_name', 'path']);
            $table->index(['target_name', 'manifest_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_target_states');
    }
};
