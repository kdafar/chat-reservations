<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique()->comment('A unique identifier, e.g., food_delivery, pharmacy');
            $table->json('name')->comment('Translatable name, e.g., {"en": "Food Delivery", "ar": "توصيل الطعام"}');
            $table->json('category_label')->comment('The label for its categories, e.g., {"en": "Cuisine", "ar": "المطبخ"}');
            $table->json('vendor_label')->comment('The label for its vendors, e.g., {"en": "Restaurant", "ar": "مطعم"}');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_types');
    }
};
