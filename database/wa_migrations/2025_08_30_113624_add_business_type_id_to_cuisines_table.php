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
        Schema::table('cuisines', function (Blueprint $table) {
            $table->foreignId('business_type_id')
                ->nullable()
                ->after('id') // Place it right after the primary key for clarity
                ->constrained('business_types') // Creates the foreign key relationship
                ->onDelete('set null'); // If a business type is deleted, this link is set to null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuisines', function (Blueprint $table) {
            $table->dropForeign(['business_type_id']);
            $table->dropColumn('business_type_id');
        });
    }
};
