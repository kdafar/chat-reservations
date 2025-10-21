<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();       // {"en": "...", "ar": "..."}
            $table->json('subtitle')->nullable();    // order text / tagline
            $table->string('hero_image_path')->nullable();
            $table->boolean('show_featured_cuisines')->default(true);
            $table->boolean('show_featured_partners')->default(true);
            $table->boolean('show_trending_items')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
    }
};
