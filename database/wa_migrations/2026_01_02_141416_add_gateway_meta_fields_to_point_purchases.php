<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('point_purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('point_purchases', 'gateway_meta')) {
                $table->json('gateway_meta')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_purchases', function (Blueprint $table) {
            $table->dropColumn('gateway_meta');
        });
    }
};
