<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('visit_id');
            $table->unsignedBigInteger('clinic_item_id');

            // Snapshot branch for reporting (optional but recommended)
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->decimal('qty', 12, 3)->default(1);

            // Snapshot fields (audit-proof)
            $table->decimal('unit_cost_snapshot', 12, 3)->default(0);
            $table->decimal('unit_price_snapshot', 12, 3)->default(0);

            // Cached line totals (helpful for reporting/UI)
            $table->decimal('line_cost_total', 12, 3)->default(0);
            $table->decimal('line_price_total', 12, 3)->default(0);

            $table->timestamps();

            $table->index(['visit_id']);
            $table->index(['clinic_item_id']);

            $table->foreign('visit_id')
                ->references('id')->on('visits')
                ->cascadeOnDelete();

            $table->foreign('clinic_item_id')
                ->references('id')->on('clinic_items')
                ->restrictOnDelete();

            $table->foreign('branch_id')
                ->references('id')->on('branches')
                ->nullOnDelete();

            // Prevent duplicate same item per visit (edit qty instead)
            $table->unique(['visit_id', 'clinic_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_items');
    }
};
