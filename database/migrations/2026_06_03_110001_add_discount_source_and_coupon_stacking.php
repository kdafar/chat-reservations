<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks WHERE a line discount came from ('promo' = auto catalog promotion,
 * 'manual' = entered by staff) so the UI can badge promo lines and coupons can
 * enforce stacking rules. `clinic_coupons.stacks_with_promotions` controls
 * whether a coupon may combine with promotion-applied discounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_items', function (Blueprint $table) {
            $table->string('discount_source', 16)->nullable()->after('discount_amount');
        });
        Schema::table('visit_packages', function (Blueprint $table) {
            $table->string('discount_source', 16)->nullable()->after('discount_amount');
        });
        Schema::table('clinic_coupons', function (Blueprint $table) {
            $table->boolean('stacks_with_promotions')->default(true)->after('max_discount');
        });
    }

    public function down(): void
    {
        Schema::table('visit_items', fn (Blueprint $t) => $t->dropColumn('discount_source'));
        Schema::table('visit_packages', fn (Blueprint $t) => $t->dropColumn('discount_source'));
        Schema::table('clinic_coupons', fn (Blueprint $t) => $t->dropColumn('stacks_with_promotions'));
    }
};
