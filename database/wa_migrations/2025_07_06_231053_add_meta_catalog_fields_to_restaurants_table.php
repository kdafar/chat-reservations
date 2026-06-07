<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            // Stores the ID for the restaurant's "product" entry in the catalog
            if (! Schema::hasColumn('restaurants', 'facebook_product_id')) {
                $table->string('facebook_product_id')->nullable()->after('id');
            }
            // Stores the ID for the "product set" that holds this restaurant's menu
            if (! Schema::hasColumn('restaurants', 'facebook_product_set_id')) {
                $table->string('facebook_product_set_id')->nullable()->after('facebook_product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['facebook_product_id', 'facebook_product_set_id']);
        });
    }
};
