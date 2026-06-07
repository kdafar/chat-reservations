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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            // Link this cart item to a specific user's session
            $table->foreignId('whatsapp_session_id')->constrained('whatsapp_sessions')->onDelete('cascade');

            // --- Item Details (copied from the restaurant's API) ---
            // We don't store the full menu, just what's in the cart.
            $table->string('item_id_from_restaurant');
            $table->string('item_name');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 8, 2);
            $table->json('variations')->nullable()->comment('Store selected variations like size or toppings as JSON.');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
