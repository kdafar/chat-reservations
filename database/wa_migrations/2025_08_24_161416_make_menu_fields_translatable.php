<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // addon_groups/addons are outside the WhatsApp module set; guard for them.
        $tables = array_filter(['menu_categories', 'menu_items', 'addon_groups', 'addons'], fn ($t) => Schema::hasTable($t));

        foreach ($tables as $table) {
            // Update existing string data to valid JSON format for the 'name' column
            DB::table($table)->whereNotNull('name')->update([
                'name' => DB::raw("JSON_OBJECT('en', name)"),
            ]);

            // For tables that have a 'description' column
            if (Schema::hasColumn($table, 'description')) {
                // Update existing string data to valid JSON format for 'description'
                DB::table($table)->whereNotNull('description')->update([
                    'description' => DB::raw("JSON_OBJECT('en', description)"),
                ]);
            }
        }

        // Now that the data is converted, we can safely change the column types
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->json('name')->change();
            $table->json('description')->nullable()->change();
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->json('name')->change();
            $table->json('description')->nullable()->change();
        });
        if (Schema::hasTable('addon_groups')) {
            Schema::table('addon_groups', function (Blueprint $table) {
                $table->json('name')->change();
            });
        }
        if (Schema::hasTable('addons')) {
            Schema::table('addons', function (Blueprint $table) {
                $table->json('name')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['menu_categories', 'menu_items', 'addon_groups', 'addons'];

        foreach ($tables as $table) {
            // Extract the 'en' value from JSON back to a plain string
            DB::table($table)->update([
                'name' => DB::raw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))"),
            ]);

            if (Schema::hasColumn($table, 'description')) {
                // Extract the 'en' value from JSON back to a plain string
                DB::table($table)->update([
                    'description' => DB::raw("JSON_UNQUOTE(JSON_EXTRACT(description, '$.en'))"),
                ]);
            }
        }

        // Now change the column types back to their original state
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->string('name')->change();
            $table->text('description')->nullable()->change();
        });
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('name')->change();
            $table->text('description')->nullable()->change();
        });
        Schema::table('addon_groups', function (Blueprint $table) {
            $table->string('name')->change();
        });
        Schema::table('addons', function (Blueprint $table) {
            $table->string('name')->change();
        });
    }
};
