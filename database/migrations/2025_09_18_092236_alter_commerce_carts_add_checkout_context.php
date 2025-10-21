<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_carts', function (Blueprint $t) {
            $t->decimal('delivery_fee', 10, 3)->nullable()->after('currency');
            $t->string('order_type', 16)->nullable()->after('delivery_fee'); // 'delivery' | 'pickup'
            $t->unsignedBigInteger('address_id')->nullable()->after('order_type');

            $t->unsignedBigInteger('coupon_id')->nullable()->after('address_id');
            $t->string('coupon_code', 64)->nullable()->after('coupon_id');
            $t->json('coupon_meta')->nullable()->after('coupon_code');

            $t->index('address_id');
            $t->index('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_carts', function (Blueprint $t) {
            $t->dropColumn(['delivery_fee', 'order_type', 'address_id', 'coupon_id', 'coupon_code', 'coupon_meta']);
        });
    }
};
