<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-clinic / per-branch configurable payment methods for the visit payment
 * modal. Scope resolution (most specific wins):
 *   - branch_id set            -> branch-specific override (within a clinic)
 *   - partner_id set, branch null -> clinic-wide default
 *   - both null                -> global system default (all clinics)
 * The resolver dedupes by `key`, most-specific row winning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_payment_methods', function (Blueprint $table) {
            $table->id();

            // Clinic scope. Null = global default shared by every clinic.
            $table->unsignedBigInteger('partner_id')->nullable();
            // Optional within-clinic branch override. Null = whole clinic.
            $table->unsignedBigInteger('branch_id')->nullable();

            $table->string('key');                              // 'cash','knet','card','transfer','insurance','link'
            $table->string('label');                            // display name
            $table->string('type')->default('manual');          // 'manual' | 'online'
            $table->boolean('requires_reference')->default(false); // needs a transaction/reference id
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['partner_id', 'branch_id', 'is_active'], 'cpm_scope_active_idx');

            $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_payment_methods');
    }
};
