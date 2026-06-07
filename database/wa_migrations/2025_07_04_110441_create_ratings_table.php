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
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();

            // Link the rating to the specific restaurant that was rated
            $table->foreignId('restaurant_id')->constrained('restaurants')->onDelete('cascade');

            // Link the rating to the user's session to know who rated
            $table->foreignId('whatsapp_session_id')->constrained('whatsapp_sessions')->onDelete('cascade');

            // The rating value, e.g., 1 to 5 stars
            $table->unsignedTinyInteger('rating');

            // An optional text comment from the user
            $table->text('comment')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
