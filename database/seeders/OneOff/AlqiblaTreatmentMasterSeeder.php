<?php

namespace Database\Seeders\OneOff;

use App\Models\ClinicalPhrase;
use App\Models\ClinicItem;
use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ONE-OFF — Alqibla Clinic Center only. Deliberately NOT registered in
 * DatabaseSeeder; run it by hand:
 *
 *     php artisan db:seed --class="Database\Seeders\OneOff\AlqiblaTreatmentMasterSeeder"
 *
 * Source: Clinic_Treatment_Master_Sheet_Bilingual (1).xlsx, extracted by
 * script into database/seeders/data/alqibla_treatment_master.php (not hand
 * transcribed, so the bilingual strings are exactly what the sheet carries).
 *
 * The workbook has four sheets. This seeder loads two of them:
 *
 *   "Products & Suppliers"    -> 8 clinic_items of type 'consumable'
 *   "Protocol & Aftercare"    -> bilingual quick-phrases for the visit console
 *
 * "Treatment Master" is already loaded by AlqiblaTreatmentPricesSeeder.
 *
 * "Equipment" is deliberately SKIPPED. Those six machines would belong in
 * fixed_assets, but every financial field on the sheet is TBC — no cost, no
 * purchase date, no useful life — and each row is marked Status: Pending, i.e.
 * not bought yet. Creating zero-cost assets would put placeholder rows in the
 * fixed-asset register and feed a depreciation schedule that means nothing.
 * Add them through v2 > Fixed Assets once they are actually purchased.
 *
 * Only the Indication and Aftercare columns of the protocol sheet are loaded.
 * Preparation, Protocol, Follow-up and Medical Approval each carry ONE
 * repeated placeholder across all 15 rows ("TBC – use approved clinic
 * protocol…"), so seeding them would add 60 identical phrases to the picker
 * and make it worse, not better.
 *
 * Products get default_cost 0: the sheet lists no prices, and every size,
 * stock level and expiry is TBC. It is a procurement planning sheet, so no
 * stock is written either.
 *
 * Idempotent — re-running updates rather than duplicating.
 */
class AlqiblaTreatmentMasterSeeder extends Seeder
{
    private const PARTNER_SLUG = 'alqibla-clinic-center';

    /** Sheet row counts, to prove nothing was dropped. */
    private const SHEET_PRODUCTS = 8;

    private const SHEET_PROTOCOLS = 15;

    public function run(): void
    {
        $partner = Partner::withoutGlobalScopes()->where('slug', self::PARTNER_SLUG)->first();

        if (! $partner) {
            $this->command?->error('Partner "'.self::PARTNER_SLUG.'" not found — this seeder is for that install only.');

            return;
        }

        $path = database_path('seeders/data/alqibla_treatment_master.php');

        if (! is_file($path)) {
            $this->command?->error("Data file missing: {$path}");

            return;
        }

        $data = require $path;

        if (! $this->reconciles($data)) {
            return;
        }

        $this->products($data['products'], $partner->id);
        $this->phrases($data['protocols']);
        $this->vendorReps($data['products']);

        $this->command?->warn('Equipment sheet skipped — all financials TBC and status Pending. Add machines in v2 > Fixed Assets once purchased.');
    }

    private function reconciles(array $data): bool
    {
        $p = count($data['products'] ?? []);
        $r = count($data['protocols'] ?? []);

        if ($p !== self::SHEET_PRODUCTS || $r !== self::SHEET_PROTOCOLS) {
            $this->command?->error(
                "Row-count mismatch — got {$p} products / {$r} protocols, "
                .'sheet has '.self::SHEET_PRODUCTS.' / '.self::SHEET_PROTOCOLS.'. Aborting.'
            );

            return false;
        }

        $this->command?->info("Reconciled: {$p} products, {$r} protocol rows.");

        return true;
    }

    /** Treatment consumables. Non-billable — they are burned during a service. */
    private function products(array $rows, int $partnerId): void
    {
        $existing = ClinicItem::withoutGlobalScopes()
            ->where('partner_id', $partnerId)
            ->whereNull('branch_id')
            ->get();

        $created = 0;
        $updated = 0;

        foreach ($rows as $r) {
            // Brand disambiguates otherwise-generic names ("Biotin vitamin").
            $en = $r['brand'] ? $r['en'].' — '.$r['brand'] : $r['en'];
            $ar = $r['ar'] ?: $en;

            $attrs = [
                'type' => 'consumable',
                'is_stockable' => true,
                // Sheet says "Size: TBC" for every row, so the unit is unknown.
                'stock_unit' => 'unit',
                'usage_unit' => 'unit',
                'conversion_factor' => 1,
                'consume_step' => 1,
                'is_billable' => false,
                'default_cost' => 0,
                'default_price' => 0,
                'is_active' => true,
            ];

            $match = $existing->first(fn (ClinicItem $i) => data_get($i->name, 'en') === $en);

            if ($match) {
                $match->update($attrs);
                $updated++;

                continue;
            }

            ClinicItem::create($attrs + [
                'partner_id' => $partnerId,
                'branch_id' => null,
                'name' => ['en' => $en, 'ar' => $ar],
            ]);
            $created++;
        }

        $this->command?->info("Products: {$created} created, {$updated} updated (cost 0 — sheet lists no prices).");
    }

    /**
     * Indication -> chief_complaint, Aftercare -> patient_instructions.
     * One phrase per locale, labelled by treatment so a doctor can pick
     * "Chemical Peel" straight out of the quick-phrase list.
     */
    private function phrases(array $rows): void
    {
        $written = 0;
        $sort = 0;

        foreach ($rows as $r) {
            $sort += 10;

            $map = [
                'chief_complaint' => ['en' => $r['indication_en'], 'ar' => $r['indication_ar']],
                'patient_instructions' => ['en' => $r['aftercare_en'], 'ar' => $r['aftercare_ar']],
            ];

            foreach ($map as $field => $bodies) {
                foreach (['en', 'ar'] as $locale) {
                    $body = $bodies[$locale] ?? null;

                    if (blank($body)) {
                        continue;
                    }

                    $label = $locale === 'ar' ? ($r['service_ar'] ?: $r['service_en']) : $r['service_en'];

                    ClinicalPhrase::updateOrCreate(
                        [
                            'field' => $field,
                            'locale' => $locale,
                            'label' => $label,
                            'scope' => 'clinic',
                            'doctor_id' => null,
                        ],
                        [
                            'body' => $body,
                            'branch_id' => null,
                            'sort_order' => $sort,
                            'is_active' => true,
                        ],
                    );
                    $written++;
                }
            }
        }

        $this->command?->info("Clinical phrases: {$written} written (indications + aftercare, EN/AR).");
    }

    /**
     * The sheet names the medical reps behind the supplier. Fill them onto the
     * vendor record so purchasing has a person to call, without overwriting a
     * contact someone has already entered.
     */
    private function vendorReps(array $rows): void
    {
        $reps = [];

        foreach ($rows as $r) {
            if ($r['supplier'] && $r['rep'] && $r['rep_phone']) {
                $reps[$r['supplier']][$r['rep']] = $r['rep_phone'];
            }
        }

        foreach ($reps as $supplier => $people) {
            $vendor = DB::table('vendors')->where('name', 'like', $supplier.'%')->first();

            if (! $vendor) {
                $this->command?->warn("Vendor '{$supplier}' not found — skipping rep contact.");

                continue;
            }

            $list = [];
            foreach ($people as $name => $phone) {
                $list[] = "{$name} {$phone}";
            }

            $first = array_key_first($people);
            $line = 'Medical reps: '.implode(' · ', $list);
            $notes = (string) $vendor->notes;

            // Append the rep line once -- re-running must not stack duplicates.
            if (! str_contains($notes, $line)) {
                $notes = trim($notes."\n".$line);
            }

            DB::table('vendors')->where('id', $vendor->id)->update([
                'contact_name' => $vendor->contact_name ?: $first,
                'phone' => $vendor->phone ?: $people[$first],
                'notes' => $notes,
                'updated_at' => now(),
            ]);

            $this->command?->info("Vendor '{$supplier}': reps ".implode(' · ', $list));
        }
    }
}
