<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy fees backfill (audit follow-up review).
     *
     * VisitCostingService now derives fees_total from SUM(visit_charges.line_total).
     * Production visits created BEFORE the charges table existed have a non-zero
     * fees_total but zero VisitCharge rows. Running compute() on them would zero
     * out their historical fees.
     *
     * This backfill inserts ONE "Consultation Fee" VisitCharge row for every
     * visit where fees_total > 0 AND no VisitCharge rows currently exist —
     * preserving the historical number as a real row that compute() will sum
     * back to the same value.
     *
     * Idempotent — re-runs find that charges already exist for the visit and skip.
     */
    public function up(): void
    {
        if (! Schema::hasTable('visits') || ! Schema::hasTable('visit_charges')) {
            return;
        }

        $rows = DB::table('visits as v')
            ->leftJoin('visit_charges as vc', 'vc.visit_id', '=', 'v.id')
            ->whereNull('vc.id')                  // No charge rows at all
            ->where('v.fees_total', '>', 0)       // But fees_total carries a value
            ->select('v.id', 'v.branch_id', 'v.fees_total', 'v.created_at')
            ->get();

        $now = now();
        foreach ($rows as $r) {
            DB::table('visit_charges')->insert([
                'visit_id' => $r->id,
                'branch_id' => $r->branch_id,
                'label' => 'Consultation Fee',
                'qty' => 1,
                'unit_price_snapshot' => $r->fees_total,
                'line_total' => $r->fees_total,
                'added_by_user_id' => null,
                'created_at' => $r->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No safe rollback: we can't tell auto-inserted from manually-added
        // 'Consultation Fee' rows after the fact. Manual cleanup if needed.
    }
};
