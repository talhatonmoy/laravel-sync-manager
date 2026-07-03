<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('url');
            $table->string('api_key')->nullable();
            $table->string('source_app_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_targets');
    }
};
