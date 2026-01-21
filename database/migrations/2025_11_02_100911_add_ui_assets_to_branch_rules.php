<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_availability_rules', function (Blueprint $table) {
            $table->json('ui_party_images')->nullable(); // map or array of assets per size
            $table->json('ui_time_image')->nullable();   // single asset for time options
        });
    }

    public function down(): void
    {
        Schema::table('branch_availability_rules', function (Blueprint $table) {
            $table->dropColumn(['ui_party_images', 'ui_time_image']);
        });
    }
};
