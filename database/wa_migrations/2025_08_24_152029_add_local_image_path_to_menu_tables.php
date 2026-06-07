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
        Schema::table('menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_items', 'local_image_path')) {
                $table->string('local_image_path')->nullable()->after('image_url');
            }
        });

        Schema::table('menu_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('menu_categories', 'local_image_path')) {
                $table->string('local_image_path')->nullable()->after('image_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('local_image_path');
        });
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropColumn('local_image_path');
        });
    }
};
