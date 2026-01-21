<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_package_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('clinic_package_id')->index();
            $table->unsignedBigInteger('clinic_item_id')->index();

            // Always base qty aligned with ClinicStockService
            $table->decimal('qty_base', 12, 4)->default(0);

            $table->boolean('is_consumable')->default(true)->index();

            $table->timestamps();

            $table->foreign('clinic_package_id')->references('id')->on('clinic_packages')->cascadeOnDelete();
            $table->foreign('clinic_item_id')->references('id')->on('clinic_items')->restrictOnDelete();

            $table->unique(['clinic_package_id', 'clinic_item_id'], 'cpi_unique_pkg_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_package_items');
    }
};
