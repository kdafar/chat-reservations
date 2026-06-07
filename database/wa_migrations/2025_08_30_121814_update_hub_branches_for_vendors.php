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
        Schema::table('hub_branches', function (Blueprint $table) {
            // Drop the existing foreign key constraint before renaming the column.
            // Laravel's convention is [table]_[column]_foreign
            $table->dropForeign(['restaurant_id']);

            // Rename the column.
            $table->renameColumn('restaurant_id', 'vendor_id');

            // Re-add the foreign key constraint with the new column name, referencing the vendors table.
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hub_branches', function (Blueprint $table) {
            // Drop the new foreign key.
            $table->dropForeign(['vendor_id']);

            // Rename the column back.
            $table->renameColumn('vendor_id', 'restaurant_id');

            // Re-add the old foreign key.
            $table->foreign('restaurant_id')->references('id')->on('vendors')->onDelete('cascade');
        });
    }
};
