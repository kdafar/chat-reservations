<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_item_stocks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('clinic_item_id');

            // Always stored in usage/base units (ml/units/pcs)
            $table->decimal('qty_on_hand_base', 12, 4)->default(0);
            $table->decimal('min_qty_threshold_base', 12, 4)->nullable();

            // Optional physical bin note
            $table->string('bin_location')->nullable();

            $table->timestamps();

            $table->unique(['branch_id', 'clinic_item_id'], 'clinic_stock_branch_item_unique');

            $table->index(['clinic_item_id']);
            $table->index(['branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_item_stocks');
    }
};
