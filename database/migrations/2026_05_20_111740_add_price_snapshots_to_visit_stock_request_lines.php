<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visit_stock_request_lines')) {
            return;
        }

        // Capture the item's cost & price at REQUEST creation time so that
        // an admin changing the item price between request and fulfillment
        // doesn't silently change historic patient invoices.
        Schema::table('visit_stock_request_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('visit_stock_request_lines', 'unit_cost_snapshot')) {
                $table->decimal('unit_cost_snapshot', 12, 3)->nullable()->after('qty_base');
            }
            if (! Schema::hasColumn('visit_stock_request_lines', 'unit_price_snapshot')) {
                $table->decimal('unit_price_snapshot', 12, 3)->nullable()->after('unit_cost_snapshot');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visit_stock_request_lines')) {
            return;
        }

        Schema::table('visit_stock_request_lines', function (Blueprint $table) {
            foreach (['unit_cost_snapshot', 'unit_price_snapshot'] as $col) {
                if (Schema::hasColumn('visit_stock_request_lines', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
