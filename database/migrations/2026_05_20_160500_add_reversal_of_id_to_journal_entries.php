<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inverse traceability for reversals (audit follow-up R3).
     *
     * journal_entries already has `reversed_by_id` (original → reversal).
     * Add `reversal_of_id` (reversal → original) as a real column with FK +
     * index, so queries like "show me everything that ever reversed entry X"
     * don't have to use JSON-path tricks on meta.
     *
     * Backfills existing reversal rows from meta['reversal_of'] (the previous
     * storage location) so historical entries remain queryable.
     */
    public function up(): void
    {
        if (! Schema::hasTable('journal_entries')) {
            return;
        }

        if (! Schema::hasColumn('journal_entries', 'reversal_of_id')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('reversal_of_id')->nullable()->after('reversed_by_id');
                $table->index('reversal_of_id', 'journal_entries_reversal_of_idx');
                $table->foreign('reversal_of_id', 'journal_entries_reversal_of_fk')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
            });
        }

        // Backfill from meta['reversal_of'] for any pre-existing reversal rows.
        // JSON_EXTRACT works on MySQL 5.7+; SQLite uses json_extract().
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("
                UPDATE journal_entries
                SET reversal_of_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.reversal_of')) AS UNSIGNED)
                WHERE reversal_of_id IS NULL
                  AND meta IS NOT NULL
                  AND JSON_EXTRACT(meta, '$.reversal_of') IS NOT NULL
            ");
        } elseif ($driver === 'sqlite') {
            DB::statement("
                UPDATE journal_entries
                SET reversal_of_id = CAST(json_extract(meta, '$.reversal_of') AS INTEGER)
                WHERE reversal_of_id IS NULL
                  AND meta IS NOT NULL
                  AND json_extract(meta, '$.reversal_of') IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('journal_entries')) {
            return;
        }
        if (Schema::hasColumn('journal_entries', 'reversal_of_id')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'mysql') {
                    $table->dropForeign('journal_entries_reversal_of_fk');
                    $table->dropIndex('journal_entries_reversal_of_idx');
                }
                $table->dropColumn('reversal_of_id');
            });
        }
    }
};
