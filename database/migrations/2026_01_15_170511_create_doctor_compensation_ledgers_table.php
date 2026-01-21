<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_compensation_ledgers', function (Blueprint $table) {
            $table->id();

            // Idempotency key: 1 ledger per visit
            $table->unsignedBigInteger('visit_id')->unique();
            $table->unsignedBigInteger('doctor_id')->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // Snapshots of rules
            $table->string('type_snapshot', 30)->nullable();
            $table->string('basis_snapshot', 30)->nullable();
            $table->decimal('rate_snapshot', 7, 3)->nullable();

            // Snapshots of financials
            $table->decimal('fees_snapshot', 12, 3)->default(0);
            $table->decimal('discount_snapshot', 12, 3)->default(0);
            $table->decimal('cost_snapshot', 12, 3)->default(0);
            $table->decimal('profit_snapshot', 12, 3)->default(0);

            $table->decimal('doctor_cut_amount', 12, 3)->default(0);

            $table->timestamps();

            $table->foreign('visit_id')
                ->references('id')->on('visits')
                ->cascadeOnDelete();

            $table->foreign('doctor_id')
                ->references('id')->on('doctors')
                ->cascadeOnDelete();

            $table->foreign('branch_id')
                ->references('id')->on('branches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_compensation_ledgers');
    }
};
