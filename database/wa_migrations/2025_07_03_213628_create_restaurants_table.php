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
        Schema::create('restaurants', function (Blueprint $table) {
            // --- Core Restaurant Details ---
            $table->id();

            // Translatable fields are stored as JSON
            // This will hold both English and Arabic names, e.g., {"en": "Pizza Palace", "ar": "قصر البيتزا"}
            $table->json('name');
            $table->json('description')->nullable();

            $table->string('logo_url')->nullable()->comment('URL for the restaurant logo to show in the list.');

            // --- API Connection Details ---
            $table->string('api_base_url')->comment('The base URL for the individual restaurant\'s backend API.');
            $table->string('api_key')->comment('A secret key to authenticate requests from the hub to the restaurant API.');

            // --- WhatsApp Specific Settings ---
            $table->string('owner_whatsapp_number')->nullable()->comment('The number to send order notifications to.');
            $table->boolean('is_visible_on_whatsapp')->default(true)->comment('Controls if this restaurant appears in the list.');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
