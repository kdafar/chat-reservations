<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_stock_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('clinic_item_id');
            $table->unsignedBigInteger('clinic_item_stock_id')->nullable();

            // Audit linkage
            $table->nullableMorphs('related'); // related_type, related_id (Visit, StockRequest, etc.)
            $table->unsignedBigInteger('performed_by')->nullable();

            // restock | consume | adjustment
            $table->string('type', 32);

            $table->decimal('qty_change_base', 12, 4); // +in / -out
            $table->decimal('before_qty_base', 12, 4)->default(0);
            $table->decimal('after_qty_base', 12, 4)->default(0);

            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index(['branch_id', 'clinic_item_id']);
            $table->index(['clinic_item_stock_id']);
            $table->index(['performed_by']);
            $table->index(['type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_stock_movements');
    }
};
