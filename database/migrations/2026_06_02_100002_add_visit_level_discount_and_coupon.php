<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visit-level discount inputs + applied coupon. The resolved KWD still lands in
 * the existing visits.discount_total (which costing/closing/doctor-pay already
 * read); these columns record HOW it was derived so percent/coupon discounts
 * recompute as the visit's lines change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Manual visit discount: 'amount' (KWD) | 'percent' (%) | null = none.
            $table->string('discount_type', 16)->nullable()->after('discount_total');
            $table->decimal('discount_value', 12, 3)->default(0)->after('discount_type');
            // Applied clinic coupon (snapshot of the code for history).
            $table->unsignedBigInteger('coupon_id')->nullable()->after('discount_value');
            $table->string('coupon_code', 64)->nullable()->after('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'coupon_id', 'coupon_code']);
        });
    }
};
