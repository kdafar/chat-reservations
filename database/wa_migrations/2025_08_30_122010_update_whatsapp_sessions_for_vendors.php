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
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // Note: We are assuming there isn't a foreign key constraint here.
            // If there is one, we would need to drop it first.
            $table->renameColumn('selected_restaurant_id', 'selected_vendor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // Revert the column name back to the original.
            $table->renameColumn('selected_vendor_id', 'selected_restaurant_id');
        });
    }
};
