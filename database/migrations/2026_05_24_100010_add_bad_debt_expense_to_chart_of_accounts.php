<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotently seeds the "Bad Debt Expense" account (6020) used by the
     * insurance claims write-off auto-posting. Safe to re-run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        $now = now();

        DB::table('chart_of_accounts')->updateOrInsert(
            // Match key — code is unique across the org.
            ['code' => '6020'],
            [
                'name' => 'Bad Debt Expense',
                'type' => 'expense',
                'currency' => 'KWD',
                'is_active' => true,
                // Required for auto-postings — block delete/rename in UI.
                'is_system' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        DB::table('chart_of_accounts')->where('code', '6020')->delete();
    }
};
