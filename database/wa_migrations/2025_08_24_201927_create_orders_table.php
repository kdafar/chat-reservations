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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('customer_phone_number')->index();
            $table->string('restaurant_order_id')->nullable()->comment('The order ID from the restaurant\'s API');
            $table->string('status'); // e.g., 'completed', 'failed'
            $table->decimal('subtotal', 8, 3);
            $table->decimal('delivery_fee', 8, 3);
            $table->decimal('discount', 8, 3);
            $table->decimal('total', 8, 3);
            $table->json('order_details')->comment('Stores address, notes, customer name, etc.');
            $table->json('api_response')->nullable()->comment('Full API response from the restaurant');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
