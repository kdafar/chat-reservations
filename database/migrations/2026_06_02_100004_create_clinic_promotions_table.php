<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-bound catalog promotions: an automatic discount on clinic items/services
 * for a date range, applied when the item is added to a visit (sets the line's
 * discount_amount), so staff don't discount each line by hand.
 *
 * Scope precedence (most specific first): a specific clinic_item, then a whole
 * type (service/consumable/product), then all items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('discount_type', 16);          // 'amount' | 'percent'
            $table->decimal('discount_value', 12, 3);      // KWD per unit, or %

            // Targeting: exactly one of item / item_type / all.
            $table->string('scope', 16)->default('all');   // 'item' | 'type' | 'all'
            $table->unsignedBigInteger('clinic_item_id')->nullable()->index();
            $table->string('item_type', 16)->nullable();   // when scope=type: service|consumable|product

            $table->unsignedBigInteger('branch_id')->nullable()->index(); // null = all branches
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('priority')->default(0); // higher wins on conflict
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('clinic_item_id')->references('id')->on('clinic_items')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_promotions');
    }
};
