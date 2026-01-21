<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (! Schema::hasColumn('doctors', 'restaurant_table_id')) {
                $table->foreignId('restaurant_table_id')
                    ->nullable()
                    ->unique() // Assuming 1 doctor per room at a time
                    ->constrained('restaurant_tables')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (Schema::hasColumn('doctors', 'restaurant_table_id')) {
                $table->dropForeign(['restaurant_table_id']);
                $table->dropColumn('restaurant_table_id');
            }
        });
    }
};
