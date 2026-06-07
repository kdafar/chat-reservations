<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_profiles', function (Blueprint $table) {
            $table->id();

            // Optional multi-brand future
            $table->unsignedBigInteger('brand_id')->nullable();

            // Channel this profile is for (e.g., whatsapp, web)
            $table->string('channel', 32)->default('whatsapp');

            // Translatable JSON fields
            $table->json('name');
            $table->json('about')->nullable();
            $table->json('open_hours')->nullable();

            // Contact / identity
            $table->string('site_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable(); // public disk path or absolute URL

            $table->boolean('is_enabled')->default(true);
            $table->integer('version')->nullable();

            // Optional audit FKs
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            $table->index(['channel', 'is_enabled']);
            $table->index(['brand_id']);

            // If you have users table:
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_profiles');
    }
};
