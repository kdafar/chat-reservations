<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('point_purchases', function (Blueprint $table) {
            // 1. Make restaurant_id nullable (requires doctrine/dbal package usually,
            // or plain SQL if not available, but using Laravel helper here)
            $table->unsignedBigInteger('restaurant_id')->nullable()->change();

            // 2. Add user_id for Fleet Customers
            // Placed after restaurant_id for logical grouping
            $table->unsignedBigInteger('user_id')->nullable()->after('restaurant_id');

            // Optional: Add foreign key constraint if 'users' table exists
            // $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('point_purchases', function (Blueprint $table) {
            // Revert changes
            $table->dropColumn('user_id');

            // We can't easily revert 'nullable' to 'not null' if data exists without defaults,
            // so we generally leave it or handle strictly if needed.
            // $table->unsignedBigInteger('restaurant_id')->nullable(false)->change();
        });
    }
};
