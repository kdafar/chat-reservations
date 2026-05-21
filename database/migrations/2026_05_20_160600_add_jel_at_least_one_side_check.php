<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB parity with the model guard on journal_entry_lines (audit R3 caveat).
     *
     * The earlier CHECK migration enforced non-negative + not-both-sides, but
     * left a gap: a row with debit=0 AND credit=0 still satisfied the DB-level
     * checks. The model rejects it; this brings the DB in line so even a raw
     * DB::statement bypassing Eloquent can't insert a zero/zero line.
     */
    public function up(): void
    {
        if (! Schema::hasTable('journal_entry_lines')) {
            return;
        }
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE journal_entry_lines DROP CHECK chk_jel_one_side_positive');
        } catch (\Throwable $e) {
            // didn't exist — fine
        }

        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_jel_one_side_positive CHECK (debit > 0 OR credit > 0)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('journal_entry_lines')) {
            return;
        }
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE journal_entry_lines DROP CHECK chk_jel_one_side_positive');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
