<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('phone', 32)->nullable(); // E.164
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('discount_applied', 12, 3);
            $table->timestamps();

            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->index(['coupon_id']);
            $table->index(['coupon_id', 'user_id']);
            $table->index(['coupon_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
