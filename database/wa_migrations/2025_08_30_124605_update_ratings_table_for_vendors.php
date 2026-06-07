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
        // Drop the old FK using the array form so the (prefixed) name is derived
        // correctly; guard in case it was never created.
        try {
            Schema::table('ratings', fn (Blueprint $table) => $table->dropForeign(['restaurant_id']));
        } catch (\Throwable $e) {
        }

        Schema::table('ratings', function (Blueprint $table) {
            // Rename the column
            $table->renameColumn('restaurant_id', 'vendor_id');

            // Add the new foreign key constraint
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign(['vendor_id']);

            // Rename the column back to the original
            $table->renameColumn('vendor_id', 'restaurant_id');

            // Re-add the old foreign key
            $table->foreign('restaurant_id')
                ->references('id')
                ->on('vendors') // Still points to vendors, as the table name doesn't change back
                ->onDelete('cascade');
        });
    }
};
