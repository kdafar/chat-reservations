<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-level source idempotency (audit follow-up #2).
     *
     * AccountingService::existingFor() checks app-level for a posted entry
     * before posting, but concurrent observer + manual calls can both pass
     * the check and both insert. Adding a unique compound index closes the
     * race window: the second insert hits SQLSTATE 23000 and the service
     * treats it as idempotent.
     *
     * Including `status` in the compound key lets the reverse() flow continue
     * to work — original ('reversed') and reversal ('posted') coexist with
     * the same source because their status differs.
     *
     * NULL source_type or source_id (manual entries) are unconstrained — the
     * compound NULL gives the row a unique slot in MySQL/SQLite's unique
     * semantics, so manual JEs aren't affected.
     */
    public function up(): void
    {
        if (! Schema::hasTable('journal_entries')) {
            return;
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unique(
                ['source_type', 'source_id', 'status'],
                'journal_entries_source_status_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('journal_entries')) {
            return;
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_source_status_unique');
        });
    }
};
