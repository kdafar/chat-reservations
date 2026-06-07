<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promotional_campaigns', function (Blueprint $table) {
            // Add a column to link the campaign to a specific restaurant.
            // This is nullable in case you have system-wide campaigns in the future.
            if (! Schema::hasColumn('promotional_campaigns', 'restaurant_id')) {
                $table->foreignId('restaurant_id')->nullable()->after('id')->constrained('restaurants');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promotional_campaigns', function (Blueprint $table) {
            // It's important to drop the foreign key constraint before dropping the column.
            $table->dropForeign(['restaurant_id']);
            $table->dropColumn('restaurant_id');
        });
    }
};
