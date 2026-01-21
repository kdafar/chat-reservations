<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_items', function (Blueprint $table) {
            // Inventory toggle
            $table->boolean('is_stockable')->default(false)->after('type');

            // Units metadata
            $table->string('stock_unit')->nullable()->after('is_stockable');         // e.g. vial/box/bottle
            $table->string('usage_unit')->nullable()->after('stock_unit');          // e.g. unit/ml/pcs
            $table->decimal('conversion_factor', 12, 4)->nullable()->after('usage_unit'); // usage per 1 stock unit

            // UI helper
            $table->decimal('consume_step', 12, 4)->nullable()->after('conversion_factor'); // e.g. 1 / 0.5 / 5

            // Optional billing toggle (consumables can be non-billable)
            $table->boolean('is_billable')->default(true)->after('consume_step');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_items', function (Blueprint $table) {
            $table->dropColumn([
                'is_stockable',
                'stock_unit',
                'usage_unit',
                'conversion_factor',
                'consume_step',
                'is_billable',
            ]);
        });
    }
};
