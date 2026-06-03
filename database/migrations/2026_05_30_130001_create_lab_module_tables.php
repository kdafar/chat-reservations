<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab module (catalog → order → result lines).
 *
 * lab_tests          — master catalog of tests offered by the clinic
 * lab_orders         — one per visit (multiple tests bundled together)
 * lab_order_items    — one row per test on an order, holds the result
 *
 * Reference ranges are kept as free-text on the catalog row (e.g.
 * "70-100 mg/dL") so we can ship without modelling sex/age-specific
 * normal ranges yet. The `flag` column on the item is set by the lab
 * tech at result-entry time (normal/low/high/critical) — no auto-detect
 * for now, since reference ranges are textual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();

            // Nullable branch_id = clinic-wide catalog entry (visible to all
            // branches); set when a specific branch maintains its own pricing.
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->string('code', 32)->index();
            $table->string('name', 191);
            $table->string('specimen_type', 64)->nullable();
            $table->string('unit', 32)->nullable();
            $table->string('reference_range', 191)->nullable();
            $table->decimal('default_price', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('doctor_id')->nullable()->index();

            $table->string('order_code', 32)->unique();

            $table->enum('status', [
                'ordered',
                'sample_collected',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('ordered')->index();

            $table->dateTime('ordered_at')->nullable();
            $table->dateTime('sample_collected_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('ordered_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lab_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained();

            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('pending')->index();

            // Result value lives as a string so we can hold "Positive",
            // "Negative", "<5", etc — not just numerics.
            $table->string('result_value', 191)->nullable();
            $table->string('result_unit', 32)->nullable();
            $table->string('reference_range_snapshot', 191)->nullable();

            $table->enum('flag', ['normal', 'low', 'high', 'critical'])
                ->nullable()
                ->index();

            $table->text('notes')->nullable();

            $table->decimal('price_snapshot', 12, 3)->default(0);

            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();

            $table->timestamps();

            $table->index(['lab_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_order_items');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('lab_tests');
    }
};
