<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all DEMO/TEST transactional + catalogue data so the real EVA Medical
 * data (chart of accounts, treatments, inventory) can be loaded into a clean
 * system.
 *
 * KEEPS configuration: branches, partners, doctors, users, roles, service
 * categories, insurers/plans, payment methods, message texts, etc.
 *
 * The table list is the transitive foreign-key closure of the transactional +
 * catalogue roots (visits, patients, clinic_items, journal_entries, …), so no
 * dangling references are left behind. chart_of_accounts is included because it
 * is reseeded immediately afterwards by AccountingChartOfAccountsSeeder.
 *
 * Destructive and pre-launch only.
 */
class EvaCleanSlateSeeder extends Seeder
{
    /**
     * Children before parents is irrelevant here — we disable FK checks and
     * truncate — but the list is kept grouped for readability.
     */
    private const TABLES = [
        // --- accounting transactions ---
        'journal_entry_lines',
        'journal_entries',
        'expenses',
        'bank_statement_lines',
        'bank_reconciliations',
        'accounting_periods',

        // --- purchasing ---
        'purchase_payments',
        'purchase_receipt_lines',
        'purchase_receipts',
        'purchase_order_lines',
        'purchase_orders',
        'vendors',

        // --- stock movements / transfers ---
        'clinic_stock_movements',
        'stock_transfer_lines',
        'stock_transfers',
        'clinic_item_stocks',

        // --- visit-level clinical ---
        'visit_payments',
        'visit_charges',
        'visit_items',
        'visit_packages',
        'visit_stock_request_lines',
        'visit_stock_requests',
        'doctor_compensation_ledgers',
        'lab_order_items',
        'lab_orders',
        'follow_up_plans',

        // --- insurance ---
        'insurance_claim_items',
        'insurance_claim_payments',
        'insurance_claim_state_logs',
        'insurance_claims',
        'insurance_preauthorizations',
        'patient_insurance_policies',

        // --- inpatient ---
        'admission_charges',
        'admission_rounds',
        'admission_bed_stays',
        'admissions',

        // --- patient records / files ---
        'patient_file_access_logs',
        'patient_files',
        'visits',
        'bookings',
        'booking_holds',
        'patients',

        // --- catalogue (replaced by EVA data) ---
        'clinic_item_components',
        'clinic_promotion_items',
        'clinic_promotion_packages',
        'clinic_promotions',
        'clinic_package_items',
        'clinic_packages',
        'clinic_items',

        // --- chart of accounts (reseeded right after) ---
        'chart_of_accounts',
    ];

    public function run(): void
    {
        $this->command?->warn('EVA clean slate: wiping demo transactional + catalogue data...');

        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    $this->command?->line("  skip (no table): {$table}");

                    continue;
                }
                $n = DB::table($table)->count();
                DB::table($table)->truncate();
                if ($n > 0) {
                    $this->command?->line("  cleared {$table} ({$n})");
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command?->info('EVA clean slate complete.');
    }
}
