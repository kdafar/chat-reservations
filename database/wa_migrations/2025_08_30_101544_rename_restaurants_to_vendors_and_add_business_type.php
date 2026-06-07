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
        // 1. Rename the table
        Schema::rename('restaurants', 'vendors');

        // 2. Add the new column to the renamed table
        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('business_type_id')
                ->nullable()
                ->after('id')
                ->constrained('business_types')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeign(['business_type_id']);
            $table->dropColumn('business_type_id');
        });

        Schema::rename('vendors', 'restaurants');
    }
};
