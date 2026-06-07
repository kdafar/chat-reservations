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
        // Check if the table and column exist before attempting to add the column
        if (Schema::hasTable('business_types') && ! Schema::hasColumn('business_types', 'is_active')) {
            Schema::table('business_types', function (Blueprint $table) {
                // Add the 'is_active' column after the 'vendor_label' column
                if (! Schema::hasColumn('business_types', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('vendor_label');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the column exists before trying to drop it
        if (Schema::hasTable('business_types') && Schema::hasColumn('business_types', 'is_active')) {
            Schema::table('business_types', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
