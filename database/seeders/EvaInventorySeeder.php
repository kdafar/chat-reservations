<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the real EVA Medical opening inventory:
 *   1. Vendors          — the named Kuwait suppliers from the order sheet.
 *   2. Consumable items  — global clinic_items of type 'consumable', at EVA
 *                          procurement unit cost.
 *   3. Opening stock     — clinic_item_stocks rows on the primary branch with
 *                          the first-order quantities.
 *
 * Source data: database/seeders/data/eva_inventory.php (parsed from the EVA
 * "First Inventory Order" workbook).
 *
 * NOTE: this seeds opening stock balances directly (no GL posting). If you want
 * the opening inventory reflected in the ledger, post an opening journal entry
 * (Dr 1150 Inventory / Cr 3x00 Opening Equity) separately.
 */
class EvaInventorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = require database_path('seeders/data/eva_inventory.php');
        $this->command?->info('Seeding '.count($rows).' EVA inventory items...');

        $branch = Branch::find(6) ?? Branch::query()->orderBy('id')->first();
        if (! $branch) {
            $this->command?->warn('No branch found — skipping opening stock.');
        }

        $payableAccountId = Account::where('code', '2110')->value('id'); // AP — Suppliers

        $vendorIds = $this->seedVendors($rows, $payableAccountId);

        $now = now();
        $items = 0;
        $stocks = 0;

        foreach ($rows as $r) {
            $cost = round((float) $r['unit_cost'], 3);
            $label = $r['brand'] !== '' && stripos($r['brand'], 'Generic') === false
                ? $r['item'].' — '.$r['brand']
                : $r['item'];

            $item = ClinicItem::create([
                'partner_id' => null,   // global — visible to every clinic
                'branch_id' => null,
                'name' => ['en' => $label, 'ar' => $label],
                'type' => 'consumable',
                'is_stockable' => true,
                'stock_unit' => $r['unit'] ?: 'unit',
                'usage_unit' => $r['unit'] ?: 'unit',
                'conversion_factor' => 1,
                'consume_step' => 1,
                'is_billable' => false,
                'default_cost' => $cost,
                'default_price' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $items++;

            if ($branch && (float) $r['qty'] > 0) {
                ClinicItemStock::create([
                    'branch_id' => $branch->id,
                    'clinic_item_id' => $item->id,
                    'qty_on_hand_base' => (float) $r['qty'],
                    'min_qty_threshold_base' => 0,
                    'bin_location' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $stocks++;
            }
        }

        $this->command?->info("Seeded {$items} consumables, {$stocks} opening-stock rows, "
            .count($vendorIds).' vendors'.($branch ? " (stock on branch #{$branch->id})" : '').'.');
    }

    /**
     * Create vendor rows for the distinct, named Kuwait suppliers. Placeholder
     * "TBC" suppliers (no confirmed agent) are skipped.
     *
     * @return array<string,int> normalized name => vendor id
     */
    private function seedVendors(array $rows, ?int $payableAccountId): array
    {
        $now = now();
        $map = [];

        foreach ($rows as $r) {
            $name = $this->normalizeSupplier($r['supplier']);
            if ($name === null || isset($map[$name])) {
                continue;
            }

            $code = $this->vendorCode($name, $map);
            $id = DB::table('vendors')->insertGetId([
                'name' => $name,
                'code' => $code,
                'default_currency' => 'KWD',
                'country' => 'Kuwait',
                'default_payment_terms_days' => 30,
                'default_payable_account_id' => $payableAccountId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $map[$name] = $id;
        }

        return $map;
    }

    /** Reduce a raw supplier cell to a clean company name, or null if unknown. */
    private function normalizeSupplier(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        // "A / B" -> first listed supplier
        $s = trim(explode(' / ', $s)[0]);
        // drop leading "TBC — " marker
        $s = preg_replace('/^TBC\s*[—\-]?\s*/u', '', $s);
        // drop trailing "[Kuwait] agent [unverified]" qualifiers (with or without a dash)
        $s = preg_replace('/\s*[—\-]\s*(Kuwait\s+)?agent.*$/iu', '', $s);
        $s = preg_replace('/\s*[—\-]\s*.*unverified.*$/iu', '', $s);
        $s = preg_replace('/\s+(Kuwait\s+)?agent(\s+unverified)?$/iu', '', $s);
        $s = trim($s);

        // "same agent" = a back-reference to the previous supplier, not a vendor.
        if ($s === '' || strtoupper($s) === 'TBC' || strcasecmp($s, 'same') === 0) {
            return null;
        }

        return $s;
    }

    /** Unique short vendor code from the name. */
    private function vendorCode(string $name, array $existing): string
    {
        $base = strtoupper(Str::slug($name, ''));
        $code = substr($base, 0, 8) ?: 'VEND';
        $codes = array_map(fn ($n) => null, $existing); // placeholder
        $taken = DB::table('vendors')->pluck('code')->filter()->map(fn ($c) => strtoupper($c))->all();
        $candidate = $code;
        $i = 1;
        while (in_array($candidate, $taken, true)) {
            $candidate = substr($code, 0, 6).$i;
            $i++;
        }

        return $candidate;
    }
}
