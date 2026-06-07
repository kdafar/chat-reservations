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
        Schema::create('restaurant_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')
                ->constrained('restaurants')
                ->cascadeOnDelete();
            $table->string('locale', 5)->index(); // 'en', 'ar'
            $table->string('keyword');            // normalized, lowercase
            $table->string('raw')->nullable();    // original input (optional)
            $table->timestamps();

            // Prevent dupes per restaurant & locale
            $table->unique(['restaurant_id', 'locale', 'keyword'], 'rk_rest_loc_key_unique');

            // Optional fulltext (MySQL 5.7+ on InnoDB); skipped on sqlite
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText('keyword');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_keywords');
    }
};
