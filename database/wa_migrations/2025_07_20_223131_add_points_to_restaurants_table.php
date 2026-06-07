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
        Schema::table('restaurants', function (Blueprint $table) {
            // Add a column to store the points balance for each restaurant.
            // It's unsigned because points cannot be negative.
            // It defaults to 0 for all new and existing restaurants.
            if (! Schema::hasColumn('restaurants', 'points')) {
                $table->unsignedInteger('points')->default(0)->after('is_visible_on_whatsapp');
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
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }
};
