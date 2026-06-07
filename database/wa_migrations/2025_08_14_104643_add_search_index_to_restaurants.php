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
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'search_index')) {
                $table->text('search_index')->nullable()->after('description'); // denormalized EN/AR text
            }
        });
        // fulltext index is MySQL-only; skip on sqlite (module dev DB)
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('restaurants', function (Blueprint $table) {
                $table->fullText('search_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropFullText(['search_index']);
            $table->dropColumn('search_index');
        });
    }
};
