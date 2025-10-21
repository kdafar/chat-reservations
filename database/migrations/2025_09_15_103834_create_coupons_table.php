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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->json('name')->nullable();                 // translatable display name
            $table->string('code', 64)->unique();             // uppercase via app
            $table->enum('discount_type', ['amount', 'percent'])->default('amount');
            $table->decimal('discount_amount', 12, 3)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('min_order_amount', 12, 3)->default(0);
            $table->integer('max_uses')->nullable();          // global usage cap
            $table->integer('uses_per_user')->nullable();     // per user/phone cap
            $table->enum('allowed_order_type', ['any', 'delivery', 'pickup'])->default('any');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            // Dynamic application options
            $table->enum('apply_to', ['order', 'matching_items'])->default('matching_items');
            $table->integer('item_limit')->nullable();        // discount top-N eligible lines
            $table->decimal('max_discount_amount', 12, 3)->nullable(); // absolute cap per order
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
