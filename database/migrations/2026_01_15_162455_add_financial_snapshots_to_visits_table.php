<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // All additive + nullable-safe.
            // If you want defaults, keep default(0) like plan.
            $table->decimal('fees_total', 12, 3)->default(0)->after('notes');
            $table->decimal('discount_total', 12, 3)->default(0)->after('fees_total');

            $table->decimal('items_cost_total', 12, 3)->default(0)->after('discount_total');
            $table->decimal('items_price_total', 12, 3)->default(0)->after('items_cost_total');

            $table->decimal('profit_total', 12, 3)->default(0)->after('items_price_total');

            $table->dateTime('computed_at')->nullable()->after('profit_total');
            $table->string('computed_version', 50)->default('v1')->after('computed_at');

            // Optional: if you commonly filter/sort by computed time
            $table->index('computed_at');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['computed_at']);

            $table->dropColumn([
                'fees_total',
                'discount_total',
                'items_cost_total',
                'items_price_total',
                'profit_total',
                'computed_at',
                'computed_version',
            ]);
        });
    }
};
