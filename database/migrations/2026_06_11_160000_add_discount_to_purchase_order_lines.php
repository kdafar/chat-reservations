<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line purchase discount: a vendor discount on each PO line, either a
 * percentage of the line or a fixed amount (in the PO currency). The discounted
 * line total is what flows into goods_total / inventory cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->string('discount_type', 8)->default('percent')->after('unit_cost'); // percent | amount
            $table->decimal('discount_value', 12, 3)->default(0)->after('discount_type'); // % or currency amount
            $table->decimal('discount_amount', 14, 3)->default(0)->after('discount_value'); // computed, PO currency
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_amount']);
        });
    }
};
