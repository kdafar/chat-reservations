<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            // Human-readable reference. Generated on first post (JE-YYYYMMDD-XXXXX).
            // Nullable so drafts can exist before posting; unique-when-not-null.
            $table->string('code', 32)->nullable()->unique();

            $table->date('entry_date');

            $table->string('narration', 500)->nullable();

            // draft  — created but not yet posted (debits/credits may not balance)
            // posted — atomic + immutable
            // reversed — original entry that has been offset by a reversal
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');

            $table->dateTime('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by_user_id')->nullable();

            // When this entry IS a reversal, points at the original it reversed.
            // When this entry HAS BEEN reversed, the original.reversed_by_id points
            // at the new offsetting entry.
            $table->unsignedBigInteger('reversed_by_id')->nullable();

            // Polymorphic link back to the clinic event that generated this entry
            // (VisitPayment, VisitCharge, ClinicStockMovement, DoctorCompensationLedger, Expense, ...).
            $table->nullableMorphs('source');

            $table->unsignedBigInteger('accounting_period_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('currency', 3)->default('KWD');

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('posted_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reversed_by_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('accounting_period_id')->references('id')->on('accounting_periods')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->index(['status', 'entry_date']);
            $table->index('entry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
