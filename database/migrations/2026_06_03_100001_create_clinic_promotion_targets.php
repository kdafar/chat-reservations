<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-target promotions: a promotion can now target a hand-picked SET of
 * clinic items (scope='items') or a set of packages (scope='packages').
 * These pivots hold those selections. Single-item / type / all scopes still
 * use the columns on clinic_promotions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_promotion_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_promotion_id')->index();
            $table->unsignedBigInteger('clinic_item_id')->index();
            $table->unique(['clinic_promotion_id', 'clinic_item_id'], 'cpi_unique');
            $table->foreign('clinic_promotion_id')->references('id')->on('clinic_promotions')->cascadeOnDelete();
            $table->foreign('clinic_item_id')->references('id')->on('clinic_items')->cascadeOnDelete();
        });

        Schema::create('clinic_promotion_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_promotion_id')->index();
            $table->unsignedBigInteger('clinic_package_id')->index();
            $table->unique(['clinic_promotion_id', 'clinic_package_id'], 'cpp_unique');
            $table->foreign('clinic_promotion_id')->references('id')->on('clinic_promotions')->cascadeOnDelete();
            $table->foreign('clinic_package_id')->references('id')->on('clinic_packages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_promotion_items');
        Schema::dropIfExists('clinic_promotion_packages');
    }
};
