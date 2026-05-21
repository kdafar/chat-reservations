<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');

            // KWD precision throughout the system (decimal:3).
            // Exactly one of debit/credit is non-zero per line.
            $table->decimal('debit', 14, 3)->default(0);
            $table->decimal('credit', 14, 3)->default(0);

            $table->string('description', 191)->nullable();

            // Dimension columns — let reports slice the GL by branch/doctor/patient
            // without joining back through the source.
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();

            $table->string('currency', 3)->default('KWD');
            $table->decimal('exchange_rate', 14, 6)->default(1);

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->nullOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->nullOnDelete();

            $table->index('journal_entry_id');
            $table->index('account_id');
            $table->index(['account_id', 'created_at']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
