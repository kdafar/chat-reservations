<?php

namespace Database\Seeders\OneOff;

use App\Models\Accounting\Account;
use App\Models\ClinicItem;
use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ONE-OFF — Alqibla Clinic Center only. Deliberately NOT registered in
 * DatabaseSeeder; run it by hand:
 *
 *     php artisan db:seed --class="Database\Seeders\OneOff\AlqiblaWeldonItemsSeeder"
 *
 * Source: Weldon_Quotation_2326.xlsx (Weldon Trading Company, 10/08/2026),
 * 18 quotation lines / 25 units / 378.200 KWD.
 *
 * Two products appear twice — a paid line plus a "Bonus / Free Item" line
 * (High performance eye pad 5+1, Lifting Booster 1+1). Those are merged into a
 * single item whose cost is the EFFECTIVE unit cost (total paid ÷ total units
 * received), so COGS reflects what was actually spent rather than the list
 * price of the paid units alone. 18 lines therefore become 16 items.
 *
 * Type is taken from the sheet itself: a line carrying a "Retail Price" note is
 * something the clinic resells, so it becomes a billable `product` at that
 * price. Everything else is professional treatment stock — `consumable`, not
 * billable, no selling price. Flip any of them on the Items screen.
 *
 * NO opening stock is written. This document is a QUOTATION, not a delivery
 * note; claiming stock the clinic may not have received would corrupt
 * inventory and COGS. Receive the goods through a purchase order instead.
 *
 * Idempotent: matches on the item name within this clinic, so re-running
 * updates costs/prices rather than duplicating the catalogue.
 */
class AlqiblaWeldonItemsSeeder extends Seeder
{
    private const PARTNER_SLUG = 'alqibla-clinic-center';

    private const VENDOR = [
        'name' => 'Weldon Trading Company',
        'code' => 'WELDON',
        'phone' => '+965 1813161',
        'email' => 'info@weldon-group.com',
        'address' => 'Salmiya, Office 28, Mariam Complex',
        'terms_days' => 60, // "Payment within 60 days from issue date of invoice"
    ];

    /**
     * [name, unit, units_received, total_paid_kwd, retail_price_or_null]
     *
     * total_paid is the line total from the sheet; unit cost is derived so the
     * bonus units dilute it correctly.
     */
    private const ITEMS = [
        ['Super Soft Cleanser 500ml',          'ml',    1, 16.0,  null],
        ['Herbal Care Lotion 500ml',           'ml',    1, 18.0,  null],
        ['Green Peel Concentrate 200ml',       'ml',    1, 20.0,  null],
        ['Green Peel Mask 45g',                'grams', 1, 90.0,  null],
        ['Calming Facial Foam 100ml',          'ml',    1, 15.0,  null],
        ['Sensiderm Cleansing Solution 200ml', 'ml',    1,  8.5,  11.0],
        ['Gel Super Purifiant 125ml',          'ml',    1, 15.0,  null],
        ['Perfect Skin Peeling 125ml',         'ml',    1, 12.0,  null],
        ['Triple Peel 150ml',                  'ml',    1, 16.5,  null],
        ['Algo Vital Mask 300gr',              'grams', 1, 18.0,  null],
        ['Black Clearing Mask 125ml',          'ml',    1, 15.0,  null],
        // 5 paid @ 9.000 + 1 bonus = 6 units for 45.000 -> 7.500 effective
        ['High performance eye pad',           'unit',  6, 45.0,  12.0],
        ['Glow Booster 5*5ml',                 'ml',    2, 32.0,  null],
        ['Hydration Booster 5*5ml',            'ml',    2, 32.0,  null],
        // 1 paid @ 16.000 + 1 bonus = 2 units for 16.000 -> 8.000 effective
        ['Lifting Booster 5*5ml',              'ml',    2, 16.0,  null],
        ['Soft Lip Balm 10ml',                 'ml',    2,  9.2,  6.0],
    ];

    /** Totals printed on the quotation — used to prove the transcription. */
    private const SHEET_TOTAL_UNITS = 25;

    private const SHEET_TOTAL_KWD = 378.2;

    public function run(): void
    {
        $partner = Partner::withoutGlobalScopes()->where('slug', self::PARTNER_SLUG)->first();

        if (! $partner) {
            $this->command?->error('Partner "'.self::PARTNER_SLUG.'" not found — this seeder is for that install only.');

            return;
        }

        if (! $this->reconciles()) {
            return;
        }

        $vendorId = $this->seedVendor();

        $created = 0;
        $updated = 0;

        foreach (self::ITEMS as [$name, $unit, $units, $total, $retail]) {
            $cost = round($total / $units, 3);
            $isProduct = $retail !== null;

            $existing = ClinicItem::withoutGlobalScopes()
                ->where('partner_id', $partner->id)
                ->whereNull('branch_id')
                ->get()
                ->first(fn (ClinicItem $i) => data_get($i->name, 'en') === $name);

            $attrs = [
                'type' => $isProduct ? 'product' : 'consumable',
                'is_stockable' => true,
                'stock_unit' => $unit,
                'usage_unit' => $unit,
                'conversion_factor' => 1,
                'consume_step' => 1,
                'is_billable' => $isProduct,
                'default_cost' => $cost,
                'default_price' => $retail !== null ? round($retail, 3) : 0,
                'is_active' => true,
            ];

            if ($existing) {
                $existing->update($attrs);
                $updated++;

                continue;
            }

            ClinicItem::create($attrs + [
                'partner_id' => $partner->id,
                'branch_id' => null,   // clinic-wide, not tied to one branch
                'name' => ['en' => $name, 'ar' => $name],
            ]);
            $created++;
        }

        $this->command?->info("Weldon catalogue: {$created} created, {$updated} updated (clinic #{$partner->id}).");
        $this->command?->info('Vendor "'.self::VENDOR['name']."\" id #{$vendorId}.");
        $this->command?->warn('No opening stock written — the source document is a quotation. Receive the goods via a purchase order.');
    }

    /**
     * Guard against a transcription slip: the merged rows must still add up to
     * the units and value printed on the quotation.
     */
    private function reconciles(): bool
    {
        $units = array_sum(array_column(self::ITEMS, 2));
        $value = round(array_sum(array_column(self::ITEMS, 3)), 3);

        if ($units !== self::SHEET_TOTAL_UNITS || abs($value - self::SHEET_TOTAL_KWD) > 0.001) {
            $this->command?->error(
                "Transcription mismatch — got {$units} units / {$value} KWD, "
                .'sheet says '.self::SHEET_TOTAL_UNITS.' units / '.self::SHEET_TOTAL_KWD.' KWD. Aborting.'
            );

            return false;
        }

        $this->command?->info("Reconciled against the sheet: {$units} units, {$value} KWD.");

        return true;
    }

    /** The supplier, so these items can be re-ordered through a purchase order. */
    private function seedVendor(): int
    {
        $existing = DB::table('vendors')->where('code', self::VENDOR['code'])->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('vendors')->insertGetId([
            'name' => self::VENDOR['name'],
            'code' => self::VENDOR['code'],
            'phone' => self::VENDOR['phone'],
            'email' => self::VENDOR['email'],
            'address' => self::VENDOR['address'],
            'default_currency' => 'KWD',
            'country' => 'Kuwait',
            'default_payment_terms_days' => self::VENDOR['terms_days'],
            'default_payable_account_id' => Account::where('code', '2110')->value('id'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
