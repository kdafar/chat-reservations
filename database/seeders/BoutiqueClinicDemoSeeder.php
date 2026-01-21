<?php

namespace Database\Seeders;

use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Models\Visit;
use App\Models\VisitItem;
use App\Services\Clinic\ClinicStockService;
use App\Services\Clinic\VisitCostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BoutiqueClinicDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve existing required FK IDs
        $branchId = (int) (DB::table('branches')->orderBy('id')->value('id') ?? 0);
        $patientId = (int) (DB::table('patients')->orderBy('id')->value('id') ?? 0);
        $doctorId = (int) (DB::table('doctors')->orderBy('id')->value('id') ?? 0);

        if (! $branchId) {
            throw new \RuntimeException('BoutiqueClinicDemoSeeder: No branches found. Create at least 1 branch first.');
        }
        if (! $patientId) {
            throw new \RuntimeException('BoutiqueClinicDemoSeeder: No patients found. Create at least 1 patient first.');
        }
        if (! $doctorId) {
            throw new \RuntimeException('BoutiqueClinicDemoSeeder: No doctors found. Create at least 1 doctor first.');
        }

        // Initialize the Stock Service
        $stockService = app(ClinicStockService::class);

        DB::transaction(function () use ($branchId, $patientId, $doctorId, $stockService) {
            /**
             * Catalog strategy:
             * - Added inventory definitions (stock_unit, usage_unit, conversion_factor)
             * - Set is_stockable = true for Consumables
             */
            $catalog = [
                // =========================
                // SERVICES (Shared) - No Stock
                // =========================
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Doctor Consultation (General)', 'ar' => 'استشارة طبيب (عام)'],
                    'type' => 'service',
                    'default_cost' => 0.000,
                    'default_price' => 15.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Doctor Consultation (Specialist)', 'ar' => 'استشارة طبيب (أخصائي)'],
                    'type' => 'service',
                    'default_cost' => 0.000,
                    'default_price' => 20.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Follow-up Visit', 'ar' => 'مراجعة'],
                    'type' => 'service',
                    'default_cost' => 0.000,
                    'default_price' => 10.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Nursing Service Fee', 'ar' => 'رسوم تمريض'],
                    'type' => 'service',
                    'default_cost' => 0.000,
                    'default_price' => 5.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Blood Pressure Check', 'ar' => 'فحص ضغط الدم'],
                    'type' => 'service',
                    'default_cost' => 0.000,
                    'default_price' => 2.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Blood Sugar Check (Glucometer)', 'ar' => 'فحص السكر (جهاز)'],
                    'type' => 'service',
                    'default_cost' => 0.150,
                    'default_price' => 3.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Nebulizer Session', 'ar' => 'جلسة بخار (نيبولايزر)'],
                    'type' => 'service',
                    'default_cost' => 0.250,
                    'default_price' => 6.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Wound Dressing (Simple)', 'ar' => 'تضميد جرح (بسيط)'],
                    'type' => 'service',
                    'default_cost' => 0.500,
                    'default_price' => 8.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'IM Injection (Service)', 'ar' => 'حقنة عضلية (خدمة)'],
                    'type' => 'service',
                    'default_cost' => 0.000,
                    'default_price' => 3.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'IV Injection (Service)', 'ar' => 'حقنة وريدية (خدمة)'],
                    'type' => 'service',
                    'default_cost' => 0.000,
                    'default_price' => 4.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],

                // =========================
                // AESTHETIC / DERM (Branch-specific) - Services
                // =========================
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Dermatology Consultation', 'ar' => 'استشارة جلدية'],
                    'type' => 'service',
                    'default_cost' => 0.000,
                    'default_price' => 25.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Chemical Peel (Basic)', 'ar' => 'تقشير كيميائي (أساسي)'],
                    'type' => 'service',
                    'default_cost' => 5.000,
                    'default_price' => 35.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'HydraFacial Session', 'ar' => 'جلسة هيدرافيشل'],
                    'type' => 'service',
                    'default_cost' => 10.000,
                    'default_price' => 50.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Microneedling Session', 'ar' => 'جلسة مايكرو نيدلينغ'],
                    'type' => 'service',
                    'default_cost' => 6.000,
                    'default_price' => 45.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'PRP Face Session', 'ar' => 'جلسة بلازما للوجه (PRP)'],
                    'type' => 'service',
                    'default_cost' => 12.000,
                    'default_price' => 80.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Botox (Per Area)', 'ar' => 'بوتوكس (لكل منطقة)'],
                    'type' => 'service',
                    'default_cost' => 25.000,
                    'default_price' => 60.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Filler (1 ml)', 'ar' => 'فيلر (1 مل)'],
                    'type' => 'service',
                    'default_cost' => 35.000,
                    'default_price' => 95.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],

                // =========================
                // CONSUMABLES (Shared) - With Inventory Data
                // =========================
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Syringe 5ml', 'ar' => 'سرنجة 5 مل'],
                    'type' => 'consumable',
                    'default_cost' => 0.080,
                    'default_price' => 0.250,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 100, // 100 in a box
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Syringe 10ml', 'ar' => 'سرنجة 10 مل'],
                    'type' => 'consumable',
                    'default_cost' => 0.100,
                    'default_price' => 0.300,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 100,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Needle 23G', 'ar' => 'إبرة 23G'],
                    'type' => 'consumable',
                    'default_cost' => 0.050,
                    'default_price' => 0.200,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 100,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Needle 25G', 'ar' => 'إبرة 25G'],
                    'type' => 'consumable',
                    'default_cost' => 0.050,
                    'default_price' => 0.200,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 100,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Alcohol Swab', 'ar' => 'مسحة كحول'],
                    'type' => 'consumable',
                    'default_cost' => 0.020,
                    'default_price' => 0.100,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 200,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Cotton Roll', 'ar' => 'قطن طبي'],
                    'type' => 'consumable',
                    'default_cost' => 0.030,
                    'default_price' => 0.150,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Pack',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 50,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Gauze Pad (10x10)', 'ar' => 'شاش (10x10)'],
                    'type' => 'consumable',
                    'default_cost' => 0.060,
                    'default_price' => 0.200,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Pack',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 100,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Bandage Roll', 'ar' => 'رباط ضاغط'],
                    'type' => 'consumable',
                    'default_cost' => 0.120,
                    'default_price' => 0.350,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Roll',
                    'conversion_factor' => 12,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Medical Tape', 'ar' => 'لاصق طبي'],
                    'type' => 'consumable',
                    'default_cost' => 0.090,
                    'default_price' => 0.300,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Roll',
                    'conversion_factor' => 24,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Gloves (Pair)', 'ar' => 'قفازات (زوج)'],
                    'type' => 'consumable',
                    'default_cost' => 0.070,
                    'default_price' => 0.250,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Pair',
                    'conversion_factor' => 50,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Face Mask', 'ar' => 'كمامة'],
                    'type' => 'consumable',
                    'default_cost' => 0.030,
                    'default_price' => 0.100,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 50,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'IV Cannula 20G', 'ar' => 'كانيولا وريد 20G'],
                    'type' => 'consumable',
                    'default_cost' => 0.250,
                    'default_price' => 0.800,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 50,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'IV Cannula 22G', 'ar' => 'كانيولا وريد 22G'],
                    'type' => 'consumable',
                    'default_cost' => 0.250,
                    'default_price' => 0.800,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 50,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Saline 0.9% (100 ml)', 'ar' => 'محلول ملحي 0.9% (100 مل)'],
                    'type' => 'consumable',
                    'default_cost' => 0.200,
                    'default_price' => 0.900,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Bottle',
                    'conversion_factor' => 24,
                ],

                // =========================
                // AESTHETIC CONSUMABLES (Branch-specific) - With Inventory
                // =========================
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Dermapen Cartridge', 'ar' => 'خرطوشة ديرمابن'],
                    'type' => 'consumable',
                    'default_cost' => 2.500,
                    'default_price' => 6.000,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Piece',
                    'conversion_factor' => 25,
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'PRP Kit', 'ar' => 'عدة PRP'],
                    'type' => 'consumable',
                    'default_cost' => 7.500,
                    'default_price' => 15.000,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Kit',
                    'conversion_factor' => 10,
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Anesthetic Cream (Dose)', 'ar' => 'كريم تخدير (جرعة)'],
                    'type' => 'consumable',
                    'default_cost' => 0.800,
                    'default_price' => 2.500,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Tube',
                    'usage_unit' => 'Dose',
                    'conversion_factor' => 30, // 30 doses per tube
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Chemical Peel Solution (Dose)', 'ar' => 'محلول تقشير (جرعة)'],
                    'type' => 'consumable',
                    'default_cost' => 3.000,
                    'default_price' => 8.000,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Bottle',
                    'usage_unit' => 'Dose',
                    'conversion_factor' => 20, // 20 doses per bottle
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Hyaluronic Acid Ampoule', 'ar' => 'أمبول هيالورونيك'],
                    'type' => 'consumable',
                    'default_cost' => 2.000,
                    'default_price' => 6.000,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Box',
                    'usage_unit' => 'Ampoule',
                    'conversion_factor' => 5, // 5 per box
                ],
                [
                    'branch_id' => $branchId,
                    'name' => ['en' => 'Vitamin C Serum (Dose)', 'ar' => 'سيروم فيتامين C (جرعة)'],
                    'type' => 'consumable',
                    'default_cost' => 1.500,
                    'default_price' => 5.000,
                    'is_active' => true,
                    'is_stockable' => true,
                    'stock_unit' => 'Bottle',
                    'usage_unit' => 'Dose',
                    'conversion_factor' => 50,
                ],

                // =========================
                // LAB / TEST SERVICES (Shared) - No Stock
                // =========================
                [
                    'branch_id' => null,
                    'name' => ['en' => 'CBC (Complete Blood Count)', 'ar' => 'تحليل CBC (صورة الدم)'],
                    'type' => 'service',
                    'default_cost' => 2.000,
                    'default_price' => 8.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'Vitamin D Test', 'ar' => 'تحليل فيتامين D'],
                    'type' => 'service',
                    'default_cost' => 4.000,
                    'default_price' => 14.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
                [
                    'branch_id' => null,
                    'name' => ['en' => 'HbA1c Test', 'ar' => 'تحليل السكر التراكمي HbA1c'],
                    'type' => 'service',
                    'default_cost' => 4.000,
                    'default_price' => 12.000,
                    'is_active' => true,
                    'is_stockable' => false,
                ],
            ];

            // ---- Create/Upsert Clinic Items & RESTOCK ----
            $created = collect();

            foreach ($catalog as $row) {
                $branchScope = $row['branch_id'] ? '=' : 'IS NULL';

                $existing = ClinicItem::query()
                    ->where('type', $row['type'])
                    ->where(function ($q) use ($row) {
                        if ($row['branch_id']) {
                            $q->where('branch_id', $row['branch_id']);
                        } else {
                            $q->whereNull('branch_id');
                        }
                    })
                    ->whereRaw("JSON_EXTRACT(name, '$.en') = ?", [$row['name']['en']])
                    ->first();

                // Prepare attributes for fill (including new inventory fields)
                $attributes = [
                    'name' => $row['name'],
                    'default_cost' => $row['default_cost'],
                    'default_price' => $row['default_price'],
                    'is_active' => $row['is_active'],
                    'is_stockable' => $row['is_stockable'] ?? false,
                    'stock_unit' => $row['stock_unit'] ?? null,
                    'usage_unit' => $row['usage_unit'] ?? null,
                    'conversion_factor' => $row['conversion_factor'] ?? 1,
                    'consume_step' => $row['consume_step'] ?? 1,
                ];

                if ($existing) {
                    $existing->forceFill($attributes)->save();
                    $created->push($existing);
                } else {
                    $row = array_merge($row, $attributes); // Ensure defaults are set
                    $existing = ClinicItem::query()->create($row);
                    $created->push($existing);
                }

                // --- RESTOCK LOGIC (Seed Inventory) ---
                if (! empty($existing->is_stockable)) {
                    // Determine which branch to stock
                    // If item is shared (branch_id=null), we stock it in the current $branchId for this demo
                    // If item is branch-specific, we stock it in that branch
                    $targetBranchId = $existing->branch_id ?? $branchId;

                    // Check if stock exists; if 0, add demo stock
                    $currentStock = ClinicItemStock::where('branch_id', $targetBranchId)
                        ->where('clinic_item_id', $existing->id)
                        ->value('qty_on_hand_base') ?? 0;

                    if ($currentStock == 0) {
                        $stockService->restock(
                            branchId: $targetBranchId,
                            item: $existing,
                            qtyStockUnits: 50, // Give 50 Boxes/Vials of everything for demo
                            qtyBase: null,
                            performedBy: 1, // System/Admin
                            notes: 'Initial Demo Seeder Stock'
                        );
                    }
                }
            }

            // ---- Create a demo Visit (completed) ----
            $visit = Visit::query()->create([
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'branch_id' => $branchId,
                'status' => 'completed',
                'checked_in_at' => now()->subMinutes(40),
                'completed_at' => now()->subMinutes(5),
                'source' => 'reception',
                'booking_code' => 'DEMO-'.now()->format('Ymd-His'),

                // Allow testing of profit formula:
                'fees_total' => 25.000,
                'discount_total' => 0.000,
            ]);

            // ---- Attach Visit Items (mix of service + consumables) ----
            // We snapshot from clinic_items at the time of attachment.
            $pick = function (string $enName) use ($created) {
                return $created->first(fn ($it) => ($it->name['en'] ?? null) === $enName);
            };

            $visitLines = [
                // common flow: consultation + a couple services + consumables
                ['name' => 'Dermatology Consultation', 'qty' => 1],
                ['name' => 'Chemical Peel (Basic)', 'qty' => 1],
                ['name' => 'Anesthetic Cream (Dose)', 'qty' => 1],
                ['name' => 'Alcohol Swab', 'qty' => 2],
                ['name' => 'Gloves (Pair)', 'qty' => 1],
                ['name' => 'Gauze Pad (10x10)', 'qty' => 2],
            ];

            foreach ($visitLines as $line) {
                $item = $pick($line['name']);
                if (! $item) {
                    continue;
                }

                $qty = (float) $line['qty'];
                $unitCost = (float) ($item->default_cost ?? 0);
                $unitPrice = (float) ($item->default_price ?? 0);

                // Use firstOrCreate because visit_items has unique(visit_id, clinic_item_id)
                VisitItem::query()->updateOrCreate(
                    [
                        'visit_id' => $visit->id,
                        'clinic_item_id' => $item->id,
                    ],
                    [
                        'branch_id' => $visit->branch_id,
                        'qty' => $qty,
                        'unit_cost_snapshot' => $unitCost,
                        'unit_price_snapshot' => $unitPrice,
                        'line_cost_total' => $qty * $unitCost,
                        'line_price_total' => $qty * $unitPrice,
                    ]
                );
            }

            // ---- Compute snapshot totals on the visit ----
            app(VisitCostingService::class)->compute($visit, null);
        });
    }
}
