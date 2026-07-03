<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_file_objects', function (Blueprint $table): void {
            $table->id();
            $table->string('hash')->unique();
            $table->unsignedBigInteger('size');
            $table->string('storage_path');
            $table->unsignedBigInteger('reference_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_file_objects');
    }
};
