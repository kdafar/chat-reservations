<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-level CHECK constraints for journal_entry_lines (audit follow-up R3).
     *
     * The model's saving() guard already rejects bad rows in app code, but a
     * raw DB::statement or an external integration could bypass the model.
     * These constraints are the last-line backstop:
     *   - debit / credit must be non-negative
     *   - a single line cannot carry BOTH a positive debit and a positive credit
     *   - at least one of debit/credit must be > 0 (added in the follow-up
     *     migration 2026_05_20_160600_add_jel_at_least_one_side_check)
     *
     * Together these four rules mirror the model's saving() guard exactly.
     *
     * MySQL 8.0.16+ enforces CHECK; older MySQL parses + ignores them. SQLite
     * doesn't support adding CHECKs via ALTER TABLE, so we skip there — the
     * model guard is exercised by the test suite, and CI sees identical
     * behavior to production for the rejection path.
     */
    public function up(): void
    {
        if (! Schema::hasTable('journal_entry_lines')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        // Drop first so this is idempotent if it ever has to re-run.
        $this->dropIfExists('chk_jel_debit_nonneg');
        $this->dropIfExists('chk_jel_credit_nonneg');
        $this->dropIfExists('chk_jel_not_both_sides');

        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_jel_debit_nonneg CHECK (debit >= 0)');
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_jel_credit_nonneg CHECK (credit >= 0)');
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_jel_not_both_sides CHECK (NOT (debit > 0 AND credit > 0))');
    }

    public function down(): void
    {
        if (! Schema::hasTable('journal_entry_lines')) {
            return;
        }
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropIfExists('chk_jel_debit_nonneg');
        $this->dropIfExists('chk_jel_credit_nonneg');
        $this->dropIfExists('chk_jel_not_both_sides');
    }

    private function dropIfExists(string $name): void
    {
        try {
            DB::statement("ALTER TABLE journal_entry_lines DROP CHECK {$name}");
        } catch (\Throwable $e) {
            // Constraint didn't exist — safe to ignore.
        }
    }
};
