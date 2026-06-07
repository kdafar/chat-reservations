<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal media table for the module's Curator shim (App\Wa\Support\Curator\Media).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curator_media', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('directory')->nullable();
            $table->string('path')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('ext')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->json('exif')->nullable();
            $table->json('curations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curator_media');
    }
};
