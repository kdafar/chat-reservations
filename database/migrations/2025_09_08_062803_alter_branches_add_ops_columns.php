<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('partner_id');
            $table->decimal('rating_avg', 3, 2)->default(0)->after('longitude');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            $table->decimal('delivery_fee', 8, 3)->default(0)->after('rating_count');
            $table->decimal('min_order_amount', 10, 3)->default(0)->after('delivery_fee');
            $table->boolean('open_for_delivery')->default(true)->after('is_available');
            $table->boolean('open_for_pickup')->default(true)->after('open_for_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'slug', 'rating_avg', 'rating_count', 'delivery_fee', 'min_order_amount',
                'open_for_delivery', 'open_for_pickup',
            ]);
        });
    }
};
