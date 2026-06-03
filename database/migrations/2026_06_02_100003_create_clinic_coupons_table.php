<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic-side coupon codes applied to a visit at checkout. Separate from the
 * commerce/restaurant Coupon model (which is tied to menus/order types).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name')->nullable();
            $table->string('discount_type', 16);            // 'amount' | 'percent'
            $table->decimal('discount_value', 12, 3);       // KWD or %
            $table->decimal('min_subtotal', 12, 3)->default(0); // minimum visit subtotal to qualify
            $table->decimal('max_discount', 12, 3)->nullable(); // cap for percent coupons (KWD)
            $table->unsignedBigInteger('branch_id')->nullable()->index(); // null = all branches
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable(); // null = unlimited
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_coupons');
    }
};
