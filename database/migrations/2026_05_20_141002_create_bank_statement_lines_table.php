<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('bank_reconciliation_id');

            $table->date('statement_date');

            $table->string('description', 500)->nullable();
            $table->string('reference', 191)->nullable();

            // Bank-statement-side amounts (NOT GL convention):
            //   debit  = money INTO the bank account (deposit / credit-to-our-account)
            //   credit = money OUT of the bank account (withdrawal / debit-from-our-account)
            // Exactly one of the two is non-zero per row.
            $table->decimal('debit', 14, 3)->default(0);
            $table->decimal('credit', 14, 3)->default(0);

            // When set, this bank line is paired with one journal_entry_lines row.
            $table->unsignedBigInteger('matched_journal_entry_line_id')->nullable();
            $table->dateTime('matched_at')->nullable();
            $table->unsignedBigInteger('matched_by_user_id')->nullable();

            $table->string('notes', 500)->nullable();

            $table->timestamps();

            $table->foreign('bank_reconciliation_id')
                ->references('id')->on('bank_reconciliations')
                ->cascadeOnDelete();

            $table->foreign('matched_journal_entry_line_id')
                ->references('id')->on('journal_entry_lines')
                ->nullOnDelete();

            $table->foreign('matched_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('bank_reconciliation_id');
            $table->index('matched_journal_entry_line_id');
            $table->index('statement_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
