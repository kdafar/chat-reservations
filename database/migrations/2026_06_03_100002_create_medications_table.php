<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight outpatient drug formulary that powers the v2 prescription
 * builder. The builder reads a row's default dose/frequency/duration as a
 * starting point, lets the doctor tweak, then composes a formatted line into
 * the visit's free-text `prescriptions` field. Not linked to stock/billing —
 * that is what clinic_items are for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->index();
            $table->string('strength', 64)->nullable();   // "500mg", "5mg/5ml"
            $table->string('form', 48)->nullable();       // cap, tab, syrup, …
            $table->string('route', 32)->nullable();      // PO, IM, topical, …
            $table->string('default_dose', 64)->nullable();        // "1", "2"
            $table->string('default_frequency', 64)->nullable();   // "q8h", "BID"
            $table->string('default_duration', 64)->nullable();    // "7 days"
            $table->string('default_instructions', 191)->nullable(); // "after food"
            $table->unsignedBigInteger('branch_id')->nullable()->index(); // null = all branches
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
