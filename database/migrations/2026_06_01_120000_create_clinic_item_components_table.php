<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service bill-of-materials (BOM): which consumable/product items a SERVICE
 * uses each time it is performed. Lets a service auto-deduct its own items
 * from stock on a visit, instead of relisting consumables in every package.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_item_components', function (Blueprint $table) {
            $table->id();

            // The parent service item (clinic_items.type = 'service').
            $table->unsignedBigInteger('service_item_id')->index();
            // The consumable/product item it uses.
            $table->unsignedBigInteger('component_item_id')->index();

            // Quantity in the component's base units, per 1 unit of the service.
            $table->decimal('qty_base', 12, 4)->default(1);

            // Optional components are listed but NOT auto-deducted (clinician adds
            // them manually when used).
            $table->boolean('is_optional')->default(false);

            $table->timestamps();

            $table->unique(['service_item_id', 'component_item_id']);

            $table->foreign('service_item_id')->references('id')->on('clinic_items')->cascadeOnDelete();
            $table->foreign('component_item_id')->references('id')->on('clinic_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_item_components');
    }
};
