<?php

namespace Database\Seeders\OneOff;

use App\Models\ClinicItem;
use App\Models\Partner;
use Illuminate\Database\Seeder;

/**
 * ONE-OFF — Alqibla Clinic Center only. Deliberately NOT registered in
 * DatabaseSeeder; run it by hand:
 *
 *     php artisan db:seed --class="Database\Seeders\OneOff\AlqiblaTreatmentPricesSeeder"
 *
 * Source: the clinic's bilingual Service / Price list (15 rows).
 *
 * Loaded as clinic_items of type 'service' — billable, non-stockable — which
 * is how the visit console bills a treatment (same shape EvaTreatmentsSeeder
 * uses). Scoped to this clinic rather than global, so it cannot leak into
 * another install's catalogue.
 *
 * Five rows quote two prices for one treatment (Face/Underarm, Face/Back,
 * small/large area, with/without home kit). A clinic_item carries ONE price,
 * so each of those becomes its own item — otherwise staff would have to
 * remember to override the price at billing time, which is how mischarges
 * happen. 15 rows therefore become 19 items.
 *
 * default_cost is left at 0: the sheet is a PRICE list and says nothing about
 * what each treatment costs to deliver. Entering a guess would feed false
 * margins into every report, so the clinic fills cost in later — until then
 * margin reports on these will read as 100%.
 *
 * Idempotent: matches on the English name within this clinic, so re-running
 * updates prices rather than duplicating the catalogue.
 */
class AlqiblaTreatmentPricesSeeder extends Seeder
{
    private const PARTNER_SLUG = 'alqibla-clinic-center';

    /** [english, arabic, price_kwd] */
    private const TREATMENTS = [
        ['Deep Hydrafacial', 'هيدرافيشل عميق', 45.0],

        // "Chemical Peel — Face 50 KD / Underarm 25 KD"
        ['Chemical Peel — Face', 'تقشير كيميائي — الوجه', 50.0],
        ['Chemical Peel — Underarm', 'تقشير كيميائي — الإبط', 25.0],

        // "Green Peel — Face 50 KD / Back 80 KD"
        ['Green Peel — Face', 'تقشير اخضر — الوجه', 50.0],
        ['Green Peel — Back', 'تقشير اخضر — الظهر', 80.0],

        ['DermaPen', 'جهاز ديرما بن', 40.0],
        ['Microneedling RF', 'مايكرونيدلينغ بتقنية RF', 100.0],
        ['Exilis (Face & Neck)', 'إكسيليس (الوجه والرقبة)', 60.0],
        ['Bleaching (Face)', 'تشقير الشعر الوبري (الوجه)', 25.0],
        ['PRP (Hair)', 'بلاسما الشعر', 50.0],
        ['PRP (Face)', 'بلاسما للوجه', 50.0],
        ['Regular Facial', 'فيشل عادي', 35.0],

        // Priced per treated area — bill quantity = number of areas.
        ['Exilis (Large Area)', 'إكسيليس (منطقة كبيرة)', 100.0],

        // "Tattoo Removal — 25 KD small area / 30 KD large area"
        ['Tattoo Removal — Small Area', 'إزالة التاتو — منطقة صغيرة', 25.0],
        ['Tattoo Removal — Large Area', 'إزالة التاتو — منطقة كبيرة', 30.0],

        ['Carbon Laser', 'كربون ليزر', 25.0],
        ['Fractional for Lips', 'فراكشنال للشفايف', 20.0],

        // "Cosmelan — 190 KD without homekit / 250 KD with homekit"
        ['Cosmelan — without Home Kit', 'كوزميلان — بدون الكيت المنزلي', 190.0],
        ['Cosmelan — with Home Kit', 'كوزميلان — مع الكيت المنزلي', 250.0],
    ];

    /** Rows on the source sheet, to prove nothing was dropped in transcription. */
    private const SHEET_ROWS = 15;

    /** Rows that quote two prices and are therefore split into two items. */
    private const SPLIT_ROWS = 4;

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

        $existing = ClinicItem::withoutGlobalScopes()
            ->where('partner_id', $partner->id)
            ->whereNull('branch_id')
            ->get();

        $created = 0;
        $updated = 0;

        foreach (self::TREATMENTS as [$en, $ar, $price]) {
            $attrs = [
                'type' => 'service',
                'is_stockable' => false,
                'stock_unit' => null,
                'usage_unit' => null,
                'conversion_factor' => 1,
                'consume_step' => 1,
                'is_billable' => true,
                // Price list only — cost is unknown, see the class docblock.
                'default_cost' => 0,
                'default_price' => round($price, 3),
                'is_active' => true,
            ];

            $match = $existing->first(fn (ClinicItem $i) => data_get($i->name, 'en') === $en);

            if ($match) {
                $match->update($attrs);
                $updated++;

                continue;
            }

            ClinicItem::create($attrs + [
                'partner_id' => $partner->id,
                'branch_id' => null,
                'name' => ['en' => $en, 'ar' => $ar],
            ]);
            $created++;
        }

        $this->command?->info("Treatment catalogue: {$created} created, {$updated} updated (clinic #{$partner->id}).");
        $this->command?->warn('default_cost is 0 on all of these — set real costs in Items before trusting margin reports.');
    }

    /** Guard against dropping a row while transcribing the sheet. */
    private function reconciles(): bool
    {
        $expected = self::SHEET_ROWS + self::SPLIT_ROWS;
        $actual = count(self::TREATMENTS);

        if ($actual !== $expected) {
            $this->command?->error(
                "Transcription mismatch — {$actual} items from ".self::SHEET_ROWS
                .' sheet rows + '.self::SPLIT_ROWS." split rows should be {$expected}. Aborting."
            );

            return false;
        }

        $this->command?->info(
            'Reconciled: '.self::SHEET_ROWS.' sheet rows -> '.$actual.' items ('
            .self::SPLIT_ROWS.' rows split on their second price).'
        );

        return true;
    }
}
