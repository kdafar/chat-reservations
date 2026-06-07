<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_message_logs', function (Blueprint $table) {
            // High precision for USD (e.g., 0.0345)
            if (! Schema::hasColumn('fleet_message_logs', 'meta_cost_usd')) {
                $table->decimal('meta_cost_usd', 10, 5)->nullable()->after('points_cost');
            }
            // High precision for KWD (e.g., 0.012)
            if (! Schema::hasColumn('fleet_message_logs', 'meta_cost_kwd')) {
                $table->decimal('meta_cost_kwd', 10, 5)->nullable()->after('meta_cost_usd');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fleet_message_logs', function (Blueprint $table) {
            $table->dropColumn(['meta_cost_usd', 'meta_cost_kwd']);
        });
    }
};
