<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bank-match uniqueness (audit follow-up #2-2).
     *
     * matched_journal_entry_line_id must be unique across ALL bank statement
     * lines — one GL entry → at most one bank statement line, otherwise two
     * bank lines could both "cover" the same GL entry and the reconciliation
     * would silently double-count.
     *
     * NULL is allowed for unmatched lines; MySQL/SQLite both treat NULL as
     * distinct in unique indexes so unmatched rows coexist freely.
     */
    public function up(): void
    {
        if (! Schema::hasTable('bank_statement_lines')) {
            return;
        }

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->unique(
                'matched_journal_entry_line_id',
                'bank_statement_lines_matched_je_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bank_statement_lines')) {
            return;
        }

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->dropUnique('bank_statement_lines_matched_je_unique');
        });
    }
};
