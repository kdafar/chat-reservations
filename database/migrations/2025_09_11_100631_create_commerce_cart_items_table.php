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
        Schema::create('commerce_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commerce_cart_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained('menu_items')->onDelete('cascade');
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 10, 3);
            $table->decimal('subtotal', 10, 3);
            $table->json('modifiers')->nullable();
            $table->string('row_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_cart_items');
    }
};
