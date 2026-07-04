<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_local_cache', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedBigInteger('mtime');
            $table->unsignedBigInteger('size');
            $table->string('hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_local_cache');
    }
};
