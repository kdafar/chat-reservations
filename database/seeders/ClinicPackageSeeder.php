<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use App\Models\ClinicPackageItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ClinicPackageSeeder extends Seeder
{
    public function run(): void
    {
        // Include ALL branches, no matter active or not
        $branches = Branch::query()->orderBy('id')->get();
        if ($branches->isEmpty()) {
            $this->command?->warn('No Branch found; skipping ClinicPackageSeeder.');

            return;
        }

        // Include ALL clinic items, no matter active or not
        $items = ClinicItem::query()
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        if ($items->isEmpty()) {
            $this->command?->warn('No ClinicItem found; skipping ClinicPackageSeeder.');

            return;
        }

        $hasCode = Schema::hasColumn((new ClinicPackage)->getTable(), 'code');

        // A helper to find item IDs by partial English/Arabic name (very forgiving).
        // If nothing matches, returns null and that line will be skipped.
        $findItemId = function (array $needles) use ($items): ?int {
            $needles = collect($needles)->filter()->map(fn ($s) => mb_strtolower(trim((string) $s)))->values();
            if ($needles->isEmpty()) {
                return null;
            }

            foreach ($items as $it) {
                $nameArr = (array) ($it->name ?? []);
                $en = mb_strtolower((string) ($nameArr['en'] ?? ''));
                $ar = mb_strtolower((string) ($nameArr['ar'] ?? ''));

                foreach ($needles as $n) {
                    if ($n !== '' && (str_contains($en, $n) || str_contains($ar, $n))) {
                        return (int) $it->id;
                    }
                }
            }

            return null;
        };

        // Define packages (Boutique Clinic baseline Kuwait)
        // qty_base must align with your base unit convention (pcs/ml/etc.)
        $packages = [
            [
                'code' => 'CONSULT_DERM',
                'name' => ['en' => 'Consultation (Dermatology)', 'ar' => 'استشارة (جلدية)'],
                'price' => 10.000,
                'lines' => [
                    // Minimal consumables used per consultation (if you track them)
                    ['needles' => ['gloves', 'قفازات'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['mask', 'كمام'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['alcohol', 'كحول', 'spirit'], 'qty_base' => 1.0000, 'is_consumable' => true],
                ],
            ],
            [
                'code' => 'LASER_SESSION_STD',
                'name' => ['en' => 'Laser Session (Standard)', 'ar' => 'جلسة ليزر (عادي)'],
                'price' => 25.000,
                'lines' => [
                    ['needles' => ['gel', 'جل'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['gloves', 'قفازات'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['wipes', 'مناديل', 'wipe'], 'qty_base' => 1.0000, 'is_consumable' => true],
                ],
            ],
            [
                'code' => 'FACIAL_BASIC',
                'name' => ['en' => 'Facial (Basic)', 'ar' => 'تنظيف بشرة (أساسي)'],
                'price' => 15.000,
                'lines' => [
                    ['needles' => ['cleanser', 'غسول'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['toner', 'تونر'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['mask', 'ماسك'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['gloves', 'قفازات'], 'qty_base' => 1.0000, 'is_consumable' => true],
                ],
            ],
            [
                'code' => 'CHEMICAL_PEEL_LIGHT',
                'name' => ['en' => 'Chemical Peel (Light)', 'ar' => 'تقشير كيميائي (خفيف)'],
                'price' => 30.000,
                'lines' => [
                    ['needles' => ['acid', 'peel', 'تقشير'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['neutralizer', 'معادل'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['gloves', 'قفازات'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['cotton', 'قطن'], 'qty_base' => 1.0000, 'is_consumable' => true],
                ],
            ],
            [
                'code' => 'INJ_BOTOX_SMALL',
                'name' => ['en' => 'Botox (Small Area)', 'ar' => 'بوتوكس (منطقة صغيرة)'],
                'price' => 90.000,
                'lines' => [
                    ['needles' => ['syringe', 'سرنجة', 'needle', 'إبرة'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['alcohol', 'كحول'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['gloves', 'قفازات'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    // If you track botox vial as an item:
                    ['needles' => ['botox'], 'qty_base' => 1.0000, 'is_consumable' => true],
                ],
            ],
            [
                'code' => 'INJ_FILLER_1ML',
                'name' => ['en' => 'Filler (1ml)', 'ar' => 'فيلر (١ مل)'],
                'price' => 120.000,
                'lines' => [
                    ['needles' => ['cannula', 'كانيولا'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['syringe', 'سرنجة', 'needle', 'إبرة'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['alcohol', 'كحول'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['gloves', 'قفازات'], 'qty_base' => 1.0000, 'is_consumable' => true],
                    ['needles' => ['filler'], 'qty_base' => 1.0000, 'is_consumable' => true],
                ],
            ],

            // Ops / workflow helper package: “Room Setup / Consumables Pack”
            // Useful when your staff just wants to request standard room consumables quickly.
            [
                'code' => 'OPS_ROOM_CONSUMABLES_PACK',
                'name' => ['en' => 'Room Consumables Pack', 'ar' => 'باك مستهلكات الغرفة'],
                'price' => 0.000,
                'lines' => [
                    ['needles' => ['gloves', 'قفازات'], 'qty_base' => 2.0000, 'is_consumable' => true],
                    ['needles' => ['mask', 'كمام'], 'qty_base' => 2.0000, 'is_consumable' => true],
                    ['needles' => ['wipes', 'مناديل', 'wipe'], 'qty_base' => 2.0000, 'is_consumable' => true],
                    ['needles' => ['alcohol', 'كحول'], 'qty_base' => 2.0000, 'is_consumable' => true],
                    ['needles' => ['cotton', 'قطن'], 'qty_base' => 2.0000, 'is_consumable' => true],
                ],
            ],
        ];

        $createOrUpdatePackage = function (int $branchId, array $pkg) use ($hasCode) {
            $identity = ['branch_id' => $branchId];

            if ($hasCode) {
                $identity['code'] = (string) $pkg['code'];
            } else {
                // Fallback: use EN name as the stable key (works fine if you don't rename EN names)
                $identity['name->en'] = (string) $pkg['name']['en'];
            }

            /** @var ClinicPackage $p */
            $p = ClinicPackage::query()->updateOrCreate(
                $identity,
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => (string) $pkg['name']['en'], 'ar' => (string) $pkg['name']['ar']],
                    'is_active' => true,
                    'default_price' => (float) $pkg['price'],
                ]
            );

            return $p;
        };

        foreach ($branches as $branch) {
            $branchId = (int) $branch->id;

            foreach ($packages as $pkg) {
                /** @var ClinicPackage $p */
                $p = $createOrUpdatePackage($branchId, $pkg);

                // Build package items by name matching; skip missing items quietly.
                $keepItemIds = [];

                foreach (($pkg['lines'] ?? []) as $ln) {
                    $itemId = $findItemId((array) ($ln['needles'] ?? []));
                    if (! $itemId) {
                        continue;
                    }

                    $keepItemIds[] = (int) $itemId;

                    ClinicPackageItem::query()->updateOrCreate(
                        [
                            'clinic_package_id' => (int) $p->id,
                            'clinic_item_id' => (int) $itemId,
                        ],
                        [
                            'qty_base' => (float) ($ln['qty_base'] ?? 1),
                            'is_consumable' => (bool) ($ln['is_consumable'] ?? true),
                        ]
                    );
                }

                // Remove old lines not in current definition (keeps seeder idempotent)
                if (! empty($keepItemIds)) {
                    ClinicPackageItem::query()
                        ->where('clinic_package_id', (int) $p->id)
                        ->whereNotIn('clinic_item_id', array_values(array_unique($keepItemIds)))
                        ->delete();
                } else {
                    // If no lines matched, do NOT delete existing lines (protect manual config)
                    // This prevents a bad match wiping a branch package.
                }
            }
        }

        $this->command?->info('ClinicPackageSeeder: seeded Boutique Clinic packages for ALL branches (including inactive), using ALL clinic items.');
    }
}
