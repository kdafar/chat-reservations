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
        Schema::table('menu_categories', function (Blueprint $table) {
            // Drop the foreign key constraint before renaming the column.
            $table->dropForeign(['restaurant_id']);

            // Rename the column.
            $table->renameColumn('restaurant_id', 'vendor_id');

            // Re-add the foreign key constraint with the new column name.
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            // Drop the new foreign key.
            $table->dropForeign(['vendor_id']);

            // Rename the column back to its original name.
            $table->renameColumn('vendor_id', 'restaurant_id');

            // Re-add the original foreign key constraint.
            $table->foreign('restaurant_id')->references('id')->on('vendors')->onDelete('cascade');
        });
    }
};
