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
        Schema::table('restaurant_keywords', function (Blueprint $table) {
            // Step 1: Drop the foreign key constraint that depends on the index.
            // Laravel's default naming is [table]_[column]_foreign.
            $table->dropForeign(['restaurant_id']);

            // Step 2: Now that the foreign key is gone, we can safely drop the unique index.
            $table->dropUnique('rk_rest_loc_key_unique');

            // Step 3: Rename the column from 'restaurant_id' to 'vendor_id'.
            $table->renameColumn('restaurant_id', 'vendor_id');

            // Step 4: Re-create the unique index with the new column name for consistency.
            $table->unique(['vendor_id', 'locale', 'keyword'], 'vendor_keywords_unique');

            // Step 5: Re-add the foreign key constraint, pointing to the 'vendors' table.
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurant_keywords', function (Blueprint $table) {
            // Revert the process in reverse order.
            // Step 1: Drop the new foreign key.
            $table->dropForeign(['vendor_id']);

            // Step 2: Drop the new unique index.
            $table->dropUnique('vendor_keywords_unique');

            // Step 3: Rename the column back to 'restaurant_id'.
            $table->renameColumn('vendor_id', 'restaurant_id');

            // Step 4: Re-create the original unique index.
            $table->unique(['restaurant_id', 'locale', 'keyword'], 'rk_rest_loc_key_unique');

            // Step 5: Re-add the original foreign key.
            $table->foreign('restaurant_id')->references('id')->on('vendors')->onDelete('cascade');
        });
    }
};
