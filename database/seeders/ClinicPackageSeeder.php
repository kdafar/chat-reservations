<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use App\Models\ClinicPackageItem;
use App\Models\VisitPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Demo packages — the MODERN model: a package is a priced BUNDLE OF SERVICES
 * (and optionally a retail product). Each service brings its own consumables
 * through its bill of materials (see ServiceBomDemoSeeder), so packages no
 * longer relist consumables.
 *
 * This seeder also RETIRES the old flat-consumable "procedure packages"
 * (Botox/Facial/Laser/etc. that duplicated services) so the demo only shows the
 * new model: it deletes the legacy packages where possible, or deactivates them
 * when visit history references them (FK is restrictOnDelete).
 *
 * Idempotent.
 */
class ClinicPackageSeeder extends Seeder
{
    /** Legacy package codes this seeder used to create — now retired. */
    private const LEGACY_CODES = [
        'CONSULT_DERM', 'LASER_SESSION_STD', 'FACIAL_BASIC', 'CHEMICAL_PEEL_LIGHT',
        'INJ_BOTOX_SMALL', 'INJ_FILLER_1ML', 'OPS_ROOM_CONSUMABLES_PACK',
    ];

    /** Legacy EN names (covers installs without a code column). */
    private const LEGACY_NAMES_EN = [
        'Consultation (Dermatology)', 'Laser Session (Standard)', 'Facial (Basic)',
        'Chemical Peel (Light)', 'Botox (Small Area)', 'Filler (1ml)', 'Room Consumables Pack',
    ];

    public function run(): void
    {
        $branches = Branch::query()->orderBy('id')->get();
        $items = ClinicItem::query()->get();
        if ($branches->isEmpty() || $items->isEmpty()) {
            $this->command?->warn('ClinicPackageSeeder: no branches/items; skipping.');

            return;
        }

        $hasCode = Schema::hasColumn((new ClinicPackage)->getTable(), 'code');

        // Resolve a catalog item by a name fragment, optionally constrained to a type.
        $find = function (string $needle, ?string $type = null) use ($items): ?ClinicItem {
            $needle = mb_strtolower($needle);

            return $items->first(function (ClinicItem $it) use ($needle, $type) {
                if ($type && $it->type !== $type) {
                    return false;
                }
                $n = (array) ($it->name ?? []);

                return str_contains(mb_strtolower((string) ($n['en'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($n['ar'] ?? '')), $needle);
            });
        };

        // New-model bundles. Each line: [name-needle, type, qty].
        $bundles = [
            [
                'code' => 'BUNDLE_SIGNATURE_GLOW',
                'name' => ['en' => 'Signature Glow Package', 'ar' => 'باقة الإشراقة المميزة'],
                'price' => 60.000,
                'lines' => [
                    ['hydrafacial', 'service', 1],
                    ['chemical peel', 'service', 1],
                    ['vit c serum', 'product', 1],
                ],
            ],
            [
                'code' => 'BUNDLE_BRIDAL',
                'name' => ['en' => 'Bridal Radiance Package', 'ar' => 'باقة إشراقة العروس'],
                'price' => 180.000,
                'lines' => [
                    ['hydrafacial', 'service', 1],
                    ['microneedling', 'service', 1],
                    ['botox', 'service', 1],
                ],
            ],
            [
                'code' => 'COURSE_ACNE_PEEL',
                'name' => ['en' => 'Acne Care Course (3 Peels)', 'ar' => 'برنامج علاج حب الشباب (٣ جلسات)'],
                'price' => 75.000,
                'lines' => [
                    ['chemical peel', 'service', 3],
                ],
            ],
            [
                'code' => 'BUNDLE_REJUV_DUO',
                'name' => ['en' => 'Skin Rejuvenation Duo', 'ar' => 'ثنائي تجديد البشرة'],
                'price' => 130.000,
                'lines' => [
                    ['microneedling', 'service', 1],
                    ['prp', 'service', 1],
                ],
            ],
            [
                'code' => 'BUNDLE_NEW_SKIN',
                'name' => ['en' => 'New Skin Starter', 'ar' => 'باقة بداية البشرة'],
                'price' => 35.000,
                'lines' => [
                    ['dermatology consultation', 'service', 1],
                    ['hydrafacial', 'service', 1],
                ],
            ],
        ];

        // Pre-resolve each bundle's line items once (catalog is shared across branches).
        $resolved = [];
        foreach ($bundles as $b) {
            $lines = [];
            foreach ($b['lines'] as [$needle, $type, $qty]) {
                if ($item = $find($needle, $type)) {
                    $lines[] = [$item, (float) $qty];
                }
            }
            if (count($lines) >= 1) {
                $resolved[] = $b + ['resolvedLines' => $lines];
            } else {
                $this->command?->warn("ClinicPackageSeeder: bundle {$b['code']} matched no catalog items; skipped.");
            }
        }

        $created = 0;
        foreach ($branches as $branch) {
            foreach ($resolved as $b) {
                $identity = $hasCode
                    ? ['branch_id' => $branch->id, 'code' => $b['code']]
                    : ['branch_id' => $branch->id, 'name->en' => $b['name']['en']];

                $pkg = ClinicPackage::updateOrCreate($identity, [
                    'branch_id' => $branch->id,
                    'name' => $b['name'],
                    'is_active' => true,
                    'default_price' => (float) $b['price'],
                ]);

                $keep = [];
                foreach ($b['resolvedLines'] as [$item, $qty]) {
                    $keep[] = (int) $item->id;
                    ClinicPackageItem::updateOrCreate(
                        ['clinic_package_id' => $pkg->id, 'clinic_item_id' => $item->id],
                        // Services/products manage their own stock (BOM / direct);
                        // the per-line flag only matters for standalone consumables.
                        ['qty_base' => (float) $qty, 'is_consumable' => false],
                    );
                }
                $pkg->items()->whereNotIn('clinic_item_id', $keep)->delete();
                $created++;
            }
        }

        $retired = $this->retireLegacy($hasCode);

        $this->command?->info("ClinicPackageSeeder: {$created} service-bundle packages upserted; {$retired} legacy procedure-packages retired.");
    }

    /**
     * Remove legacy flat-consumable packages. Delete when unreferenced; otherwise
     * deactivate (visit history FK is restrictOnDelete) so they vanish from the
     * active demo without losing past records.
     */
    private function retireLegacy(bool $hasCode): int
    {
        $query = ClinicPackage::query()->where(function ($w) use ($hasCode) {
            if ($hasCode) {
                $w->whereIn('code', self::LEGACY_CODES);
            }
            foreach (self::LEGACY_NAMES_EN as $name) {
                $w->orWhere('name->en', $name);
            }
        });

        $count = 0;
        foreach ($query->get() as $pkg) {
            if (VisitPackage::query()->where('clinic_package_id', $pkg->id)->exists()) {
                if ($pkg->is_active) {
                    $pkg->update(['is_active' => false]);
                    $count++;
                }

                continue;
            }

            $pkg->items()->delete();
            $pkg->delete();
            $count++;
        }

        return $count;
    }
}
