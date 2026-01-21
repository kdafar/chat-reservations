<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_stock_request_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('visit_stock_request_id')->index();
            $table->unsignedBigInteger('clinic_item_id')->index();

            // Always store BASE qty here (aligned with ClinicStockService)
            $table->decimal('qty_base', 12, 4)->default(0);

            $table->timestamps();

            $table->foreign('visit_stock_request_id')
                ->references('id')
                ->on('visit_stock_requests')
                ->cascadeOnDelete();

            $table->foreign('clinic_item_id')
                ->references('id')
                ->on('clinic_items')
                ->restrictOnDelete();

            // Prevent duplicate item rows inside the same request
            $table->unique(['visit_stock_request_id', 'clinic_item_id'], 'vsrl_unique_req_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_stock_request_lines');
    }
};
