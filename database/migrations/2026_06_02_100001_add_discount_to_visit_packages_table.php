<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line discount on a visit package, mirroring visit_items.discount_amount.
 * Subtracted from line_total when computing visit.packages_price_total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_packages', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 3)->default(0)->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('visit_packages', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
