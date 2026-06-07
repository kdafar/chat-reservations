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
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            // The customer's WhatsApp number (e.g., "96541076750")
            $table->string('customer_phone_number')->unique();

            // Tracks the current state of the conversation
            $table->string('status')->default('active'); // e.g., 'active', 'ordering', 'payment_pending', 'completed'

            // Link to the restaurant the customer has chosen
            $table->foreignId('selected_restaurant_id')->nullable()->constrained('restaurants')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
