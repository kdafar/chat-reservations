<?php

namespace Database\Seeders\Demo;

use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\ClinicItem;
use App\Models\ClinicItemStock;
use App\Models\ClinicPackage;
use App\Models\ClinicPromotion;
use App\Models\ClinicStockMovement;
use App\Models\Doctor;
use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\InsurancePreauthorization;
use App\Models\PatientFile;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitPackage;
use App\Services\Clinic\ClinicStockService;
use App\Services\Clinic\StockTransferService;
use App\Services\Insurance\InsuranceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Operational depth for the reports that currently read as empty or one-sided.
 *
 * The big one is stock: every stock movement in the system is a `purchase_in`,
 * because services carry no bill-of-materials, so nothing is ever consumed.
 * Inventory only ever grows and the P&L shows no consumable COGS at all. This
 * seeder wires BOM onto the service catalogue and replays consumption for the
 * visits that already happened — the consume movements post COGS through the
 * existing accounting observer.
 */
class DemoOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBom();
        $this->seedReorderThresholds();
        $this->replayConsumption();
        $this->seedStockAdjustments();
        $this->seedStockTransfers();
        $this->seedVisitPackages();
        $this->seedPromotions();
        $this->seedInsurance();
        $this->seedDoctorShifts();
        $this->seedPatientFiles();
    }

    // -------------------------------------------------------------------------
    // STOCK
    // -------------------------------------------------------------------------

    /** Attach 1–3 consumables to every billable service so treatments draw stock. */
    protected function seedBom(): void
    {
        if (DB::table('clinic_item_components')->exists()) {
            $this->command?->warn('DemoOperationsSeeder: BOM already defined — skipping.');

            return;
        }

        $rows = [];
        foreach (Branch::query()->pluck('partner_id', 'id')->unique() as $partnerId) {
            $services = ClinicItem::query()->withoutGlobalScopes()
                ->where('type', 'service')->where('partner_id', $partnerId)->orderBy('id')->get(['id']);
            $consumables = ClinicItem::query()->withoutGlobalScopes()
                ->where('type', 'consumable')->where('partner_id', $partnerId)->orderBy('id')->get(['id']);
            if ($services->isEmpty() || $consumables->isEmpty()) {
                continue;
            }

            foreach ($services as $si => $service) {
                $count = 1 + ($si % 3);
                for ($k = 0; $k < $count; $k++) {
                    $component = $consumables[($si * 2 + $k) % $consumables->count()];
                    $rows[] = [
                        'service_item_id' => $service->id,
                        'component_item_id' => $component->id,
                        // Small draws — a treatment uses a fraction of a vial or
                        // a couple of disposables, not whole boxes.
                        'qty_base' => [0.5, 1, 1, 2, 3][($si + $k) % 5],
                        'is_optional' => ($k === 2),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (! $rows) {
            $this->command?->warn('DemoOperationsSeeder: no services/consumables to build BOM from.');

            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('clinic_item_components')->insert($chunk);
        }
        $this->command?->info('DemoOperationsSeeder: defined '.count($rows).' bill-of-material lines.');
    }

    /** Without a threshold nothing can ever be "below reorder point". */
    protected function seedReorderThresholds(): void
    {
        if (ClinicItemStock::query()->where('min_qty_threshold_base', '>', 0)->exists()) {
            return;
        }

        $updated = 0;
        foreach (ClinicItemStock::query()->orderBy('id')->cursor() as $stock) {
            // ~30% of the current holding, so a normal month of consumption
            // pushes a realistic slice of the catalogue under the line.
            $stock->min_qty_threshold_base = max(2, round((float) $stock->qty_on_hand_base * 0.3));
            $stock->saveQuietly();
            $updated++;
        }

        $this->command?->info("DemoOperationsSeeder: set reorder thresholds on {$updated} stock rows.");
    }

    /**
     * Replay BOM consumption for visits that already happened, so the stock
     * ledger has outflows and the P&L carries consumable COGS.
     */
    protected function replayConsumption(): void
    {
        if (ClinicStockMovement::query()->where('type', 'consume')->exists()) {
            $this->command?->warn('DemoOperationsSeeder: consumption already replayed — skipping.');

            return;
        }

        $stock = app(ClinicStockService::class);
        if (! $stock->enabled()) {
            $this->command?->warn('DemoOperationsSeeder: inventory disabled in config — skipping consumption.');

            return;
        }

        $bom = DB::table('clinic_item_components')
            ->where('is_optional', false)
            ->get()
            ->groupBy('service_item_id');

        $items = DB::table('visit_items')
            ->join('visits', 'visits.id', '=', 'visit_items.visit_id')
            ->select('visit_items.*', 'visits.completed_at', 'visits.computed_at')
            ->orderBy('visit_items.id')
            ->get();

        $moved = 0;
        $skipped = 0;

        foreach ($items as $line) {
            $components = $bom->get($line->clinic_item_id);
            if (! $components) {
                continue;
            }
            $item = ClinicItem::query()->withoutGlobalScopes()->find($line->clinic_item_id);

            foreach ($components as $component) {
                $componentItem = ClinicItem::query()->withoutGlobalScopes()->find($component->component_item_id);
                if (! $componentItem) {
                    continue;
                }
                $need = round((float) $component->qty_base * (float) $line->qty, 4);
                if ($need <= 0) {
                    continue;
                }

                // Never drive inventory negative — the purchase receipts are the
                // only inflow, so a shortfall means the branch genuinely never
                // stocked that consumable.
                if ($stock->availableBase((int) $line->branch_id, $componentItem->id) < $need) {
                    $skipped++;

                    continue;
                }

                try {
                    $stock->consume(
                        branchId: (int) $line->branch_id,
                        item: $componentItem,
                        qtyBaseToConsume: $need,
                        performedBy: 0,
                        notes: 'Consumed by '.$this->itemName($item).' on visit #'.$line->visit_id,
                    );
                    $this->backdateLastMovement($line->completed_at ?? $line->computed_at);
                    $moved++;
                } catch (\Throwable $e) {
                    $skipped++;
                }
            }
        }

        $this->command?->info("DemoOperationsSeeder: replayed {$moved} consumption movements ({$skipped} skipped for insufficient stock).");
    }

    /** Periodic count corrections and expiry write-offs. */
    protected function seedStockAdjustments(): void
    {
        if (ClinicStockMovement::query()->where('type', 'adjust')->exists()) {
            return;
        }

        $stock = app(ClinicStockService::class);
        $reasons = [
            'Physical count correction — monthly stock take',
            'Expired stock written off',
            'Damaged in transit — discarded',
            'Recount after cycle-count variance',
        ];

        $made = 0;
        foreach (Branch::query()->pluck('id') as $bi => $branchId) {
            $rows = ClinicItemStock::query()->where('branch_id', $branchId)
                ->where('qty_on_hand_base', '>', 5)->inRandomOrder()->limit(4)->get();

            foreach ($rows as $ri => $row) {
                $item = ClinicItem::query()->withoutGlobalScopes()->find($row->clinic_item_id);
                if (! $item) {
                    continue;
                }
                $qty = min(round((float) $row->qty_on_hand_base * 0.1, 2), 3);
                if ($qty <= 0) {
                    continue;
                }

                try {
                    // Adjustments both ways — a stock take that finds more is as
                    // real as one that finds less.
                    if (($bi + $ri) % 3 === 0) {
                        $stock->restock($branchId, $item, null, $qty, 0, $reasons[0], null, 'adjust');
                    } else {
                        $stock->consume($branchId, $item, $qty, 0, $reasons[($ri % 3) + 1], null, 'adjust');
                    }
                    $this->backdateLastMovement(Carbon::today()->subDays(random_int(3, 90)));
                    $made++;
                } catch (\Throwable $e) {
                    // insufficient stock — skip
                }
            }
        }

        $this->command?->info("DemoOperationsSeeder: created {$made} stock adjustments.");
    }

    /** Hub → spoke replenishment, which is what the stock-transfer screen is for. */
    protected function seedStockTransfers(): void
    {
        if (DB::table('stock_transfers')->exists()) {
            $this->command?->warn('DemoOperationsSeeder: stock transfers already exist — skipping.');

            return;
        }

        $service = app(StockTransferService::class);
        $requester = User::query()->value('id') ?? 0;
        $made = 0;

        foreach (Branch::query()->orderBy('id')->get(['id', 'partner_id', 'is_hub']) as $branch) {
            if ($branch->is_hub) {
                continue;
            }
            $hubId = $service->hubBranchId($branch->partner_id);
            if (! $hubId) {
                continue;
            }

            // Two transfers per spoke: one dispatched, one still requested.
            for ($n = 0; $n < 2; $n++) {
                $lines = ClinicItemStock::query()
                    ->where('branch_id', $hubId)->where('qty_on_hand_base', '>', 6)
                    ->inRandomOrder()->limit(3)->get()
                    ->map(fn ($s) => ['clinic_item_id' => $s->clinic_item_id, 'qty_base' => min(3, floor((float) $s->qty_on_hand_base / 3))])
                    ->filter(fn ($l) => $l['qty_base'] > 0)
                    ->values()->all();
                if (! $lines) {
                    continue;
                }

                try {
                    $transfer = $service->create(
                        partnerId: (int) $branch->partner_id,
                        fromBranchId: $hubId,
                        toBranchId: (int) $branch->id,
                        lines: $lines,
                        requestedBy: $requester,
                        notes: $n === 0 ? 'Weekly replenishment from the central store.' : 'Top-up request — injectables running low.',
                    );
                    $when = Carbon::today()->subDays(random_int(2, 45));
                    $transfer->forceFill(['created_at' => $when, 'updated_at' => $when])->save();

                    if ($n === 0) {
                        $service->dispatch($transfer->fresh(), $requester);
                        $transfer->fresh()->forceFill(['dispatched_at' => $when->copy()->addHours(4)])->save();
                    }
                    $made++;
                } catch (\Throwable $e) {
                    $this->command?->warn("  transfer to branch {$branch->id} failed: {$e->getMessage()}");
                }
            }
        }

        $this->command?->info("DemoOperationsSeeder: created {$made} stock transfers.");
    }

    // -------------------------------------------------------------------------
    // PACKAGES, PROMOTIONS
    // -------------------------------------------------------------------------

    /**
     * Visits already carry a packages_price_total, but the line table behind it
     * is empty — so nothing can report which package actually sold.
     */
    protected function seedVisitPackages(): void
    {
        if (VisitPackage::query()->withoutGlobalScopes()->exists()) {
            $this->command?->warn('DemoOperationsSeeder: visit packages already exist — skipping.');

            return;
        }

        $packages = ClinicPackage::query()->withoutGlobalScopes()->where('is_active', true)->get();
        if ($packages->isEmpty()) {
            return;
        }

        $staff = User::query()->value('id');
        $made = 0;

        // Backfill the visits that were billed a package, then sell a package on
        // a slice of the rest so the package report has volume.
        $withTotal = Visit::query()->withoutGlobalScopes()->where('packages_price_total', '>', 0)->get();
        foreach ($withTotal as $i => $visit) {
            $package = $packages[$i % $packages->count()];
            VisitPackage::create([
                'visit_id' => $visit->id,
                'clinic_package_id' => $package->id,
                'branch_id' => $visit->branch_id,
                'qty' => 1,
                'unit_price_snapshot' => (float) $visit->packages_price_total,
                'line_total' => (float) $visit->packages_price_total,
                'discount_amount' => 0,
                'added_by_user_id' => $staff,
                'created_at' => $visit->computed_at ?? $visit->created_at,
                'updated_at' => $visit->computed_at ?? $visit->created_at,
            ]);
            $made++;
        }

        $this->command?->info("DemoOperationsSeeder: created {$made} visit package lines.");
    }

    protected function seedPromotions(): void
    {
        if (ClinicPromotion::query()->withoutGlobalScopes()->exists()) {
            $this->command?->warn('DemoOperationsSeeder: promotions already exist — skipping.');

            return;
        }

        // [name, type, value, scope, item type, months back, months long]
        $templates = [
            ['Summer Glow — 15% off all facials', 'percent', 15, 'item_type', 'service', 2, 3],
            ['Laser Hair Removal — 20% off packages', 'percent', 20, 'all', null, 1, 2],
            ['New Patient Welcome — 10 KWD off', 'amount', 10.000, 'all', null, 4, 12],
            ['National Day Offer — 25% off injectables', 'percent', 25, 'item_type', 'service', 8, 1],
            ['Skincare Retail — 12% off products', 'percent', 12, 'item_type', 'consumable', 0, 2],
            ['Ramadan Package Deal — 30% off', 'percent', 30, 'all', null, 6, 1],
            ['Refer a Friend — 5 KWD credit', 'amount', 5.000, 'all', null, 3, 6],
            ['Loyalty Tier — 8% standing discount', 'percent', 8, 'all', null, 10, 24],
        ];

        $branches = Branch::query()->pluck('id')->all();
        $made = 0;

        foreach ($templates as $i => [$name, $type, $value, $scope, $itemType, $back, $length]) {
            $start = Carbon::today()->startOfMonth()->subMonths($back);
            $end = $start->copy()->addMonths($length)->subDay();

            ClinicPromotion::create([
                'name' => $name,
                'discount_type' => $type,
                'discount_value' => $value,
                'scope' => $scope,
                'item_type' => $itemType,
                // Half run estate-wide, half are branch-local campaigns.
                'branch_id' => $i % 2 === 0 ? null : $branches[$i % count($branches)],
                'starts_at' => $start->toDateString(),
                'ends_at' => $end->toDateString(),
                'priority' => 10 - $i,
                'is_active' => $end->isFuture(),
                'created_at' => $start->copy()->subDays(7),
                'updated_at' => $start->copy()->subDays(7),
            ]);
            $made++;
        }

        $this->command?->info("DemoOperationsSeeder: created {$made} promotions.");
    }

    // -------------------------------------------------------------------------
    // INSURANCE
    // -------------------------------------------------------------------------

    /**
     * Six claims sit at status `paid` with paid_amount = 0 and no payment rows,
     * so insurer AR is overstated and there is nothing to age against. Record
     * the payments (which post the cash through the accounting observer) and add
     * a pre-authorization pipeline.
     */
    protected function seedInsurance(): void
    {
        $insurance = app(InsuranceService::class);
        $user = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['accountant', 'clinic_admin', 'admin']))->first()
            ?? User::query()->first();
        $bank = Account::query()->where('code', '1120')->value('id');

        if (! $user) {
            return;
        }

        $paid = 0;
        if (! DB::table('insurance_claim_payments')->exists()) {
            $claims = InsuranceClaim::query()->withoutGlobalScopes()
                ->whereIn('status', [InsuranceClaim::STATUS_APPROVED ?? 'approved', 'partially_approved', 'paid'])
                ->get();

            foreach ($claims as $i => $claim) {
                $balance = round((float) $claim->balanceDue(), 3);
                if ($balance <= 0) {
                    continue;
                }
                // Most settle in full; a few part-pay so the aging report has
                // genuinely outstanding balances to chase.
                $amount = $i % 4 === 3 ? round($balance * 0.6, 3) : $balance;
                if ($amount <= 0) {
                    continue;
                }

                try {
                    $payment = $insurance->recordInsurerPayment(
                        claim: $claim,
                        amount: $amount,
                        method: ['transfer', 'transfer', 'cheque'][$i % 3],
                        referenceNo: 'REM-'.Carbon::today()->format('y').'-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                        depositedToAccountId: $bank,
                        user: $user,
                    );
                    // The service stamps now(); insurers settle weeks after the claim.
                    $when = Carbon::parse($claim->decided_at ?? $claim->submitted_at ?? Carbon::today()->subDays(30))
                        ->addDays(random_int(14, 55));
                    if ($when->isFuture()) {
                        $when = Carbon::today()->subDays(random_int(1, 10));
                    }
                    $payment->forceFill(['paid_at' => $when])->saveQuietly();
                    $paid++;
                } catch (\Throwable $e) {
                    $this->command?->warn("  claim #{$claim->id} payment failed: {$e->getMessage()}");
                }
            }
        }

        $preauths = 0;
        if (! InsurancePreauthorization::query()->withoutGlobalScopes()->exists()) {
            $policies = DB::table('patient_insurance_policies')->inRandomOrder()->limit(30)->get();
            $services = [
                ['Laser hair removal — full body course', 6],
                ['Botulinum toxin — glabellar & forehead', 2],
                ['Dermal filler — nasolabial folds', 2],
                ['HIFU facial lifting course', 4],
                ['Excision of benign skin lesion', 1],
                ['Chemical peel course', 3],
            ];

            foreach ($policies as $i => $policy) {
                [$label, $sessions] = $services[$i % count($services)];
                $estimate = round($sessions * random_int(25, 90), 3);
                // A pipeline, not a single state: some awaiting a decision, most
                // decided, a couple expired.
                $status = ['approved', 'approved', 'partially_approved', 'submitted', 'under_review', 'rejected', 'expired', 'draft'][$i % 8];
                $requestedAt = Carbon::today()->subDays(random_int(5, 120))->setTime(random_int(9, 16), 0);
                $decided = in_array($status, ['approved', 'partially_approved', 'rejected'], true);

                InsurancePreauthorization::create([
                    'patient_policy_id' => $policy->id,
                    'branch_id' => $policy->branch_id ?? Branch::query()->value('id'),
                    'requested_by_user_id' => $user->id,
                    'services' => [['label' => $label, 'sessions' => $sessions, 'estimate' => $estimate]],
                    'estimated_total' => $estimate,
                    'requested_at' => $requestedAt,
                    'reference_no' => 'PRE-'.$requestedAt->format('ym').'-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'status' => $status,
                    'approved_amount' => match ($status) {
                        'approved' => $estimate,
                        'partially_approved' => round($estimate * 0.65, 3),
                        default => 0,
                    },
                    'valid_from' => $decided ? $requestedAt->copy()->addDays(3)->toDateString() : null,
                    'valid_until' => $decided ? $requestedAt->copy()->addDays(93)->toDateString() : null,
                    'decision_notes' => match ($status) {
                        'partially_approved' => 'Approved for 4 of the 6 requested sessions; remainder requires a clinical review.',
                        'rejected' => 'Treatment considered cosmetic and excluded under the policy schedule.',
                        'expired' => 'Approval window lapsed before the treatment was started.',
                        default => null,
                    },
                    'decided_at' => $decided ? $requestedAt->copy()->addDays(random_int(2, 12)) : null,
                    'decided_by_user_id' => $decided ? $user->id : null,
                    'created_at' => $requestedAt,
                    'updated_at' => $requestedAt,
                ]);
                $preauths++;
            }
        }

        $this->command?->info("DemoOperationsSeeder: recorded {$paid} insurer payments and {$preauths} pre-authorizations.");
    }

    // -------------------------------------------------------------------------
    // SCHEDULING & FILES
    // -------------------------------------------------------------------------

    /** Published rotas, so capacity/utilisation has a denominator. */
    protected function seedDoctorShifts(): void
    {
        if (DB::table('doctor_shifts')->exists()) {
            return;
        }

        $rows = [];
        $doctors = Doctor::query()->withoutGlobalScopes()->where('is_active', true)->get(['id', 'branch_id']);

        foreach ($doctors as $di => $doctor) {
            if (! $doctor->branch_id) {
                continue;
            }
            for ($d = -30; $d <= 30; $d++) {
                $date = Carbon::today()->addDays($d);
                if ($date->isFriday()) {
                    continue;
                }
                // Doctors work an alternating morning/evening rota, with one
                // rostered day off a week.
                if (($di + $date->dayOfYear) % 6 === 0) {
                    continue;
                }
                $morning = ($di + $date->dayOfYear) % 2 === 0;

                $rows[] = [
                    'doctor_id' => $doctor->id,
                    'branch_id' => $doctor->branch_id,
                    'shift_date' => $date->toDateString(),
                    'start_time' => $morning ? '09:00:00' : '15:00:00',
                    'end_time' => $morning ? '15:00:00' : '21:00:00',
                    'break_minutes' => 30,
                    'is_cancelled' => ($di + $d) % 47 === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('doctor_shifts')->insert($chunk);
        }

        // Public holidays / maintenance days the booking grid must respect.
        $blackouts = [];
        foreach (Branch::query()->pluck('id') as $branchId) {
            foreach ([['National Day holiday', 12], ['Liberation Day holiday', 13], ['Annual deep-clean & maintenance', -20]] as [$reason, $offset]) {
                $blackouts[] = [
                    'branch_id' => $branchId,
                    'date' => Carbon::today()->addDays($offset)->toDateString(),
                    'reason' => $reason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        DB::table('branch_blackouts')->insert($blackouts);

        $this->command?->info('DemoOperationsSeeder: created '.count($rows).' doctor shifts and '.count($blackouts).' blackout days.');
    }

    /**
     * Document metadata only — no blobs are written. The reports and the patient
     * file list read the row, and the access log is produced by the observer.
     */
    protected function seedPatientFiles(): void
    {
        if (PatientFile::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        $catalogue = [
            ['lab_report', 'CBC-and-CRP-report.pdf', 'application/pdf', 184320],
            ['lab_report', 'Vitamin-D-and-ferritin.pdf', 'application/pdf', 96256],
            ['imaging', 'Ultrasound-abdomen.jpg', 'image/jpeg', 1548288],
            ['prescription', 'Prescription-signed.pdf', 'application/pdf', 72704],
            ['insurance_card', 'Insurance-card-front.jpg', 'image/jpeg', 421888],
            ['consent_form', 'Consent-laser-treatment.pdf', 'application/pdf', 133120],
            ['referral', 'Referral-to-dermatology.pdf', 'application/pdf', 88064],
            ['discharge_summary', 'Discharge-summary.pdf', 'application/pdf', 156672],
        ];

        $uploader = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['clinic_reception', 'clinic_nurse']))->value('id')
            ?? User::query()->value('id');

        $visits = Visit::query()->withoutGlobalScopes()->inRandomOrder()->limit(70)->get(['id', 'patient_id', 'branch_id', 'computed_at']);
        $made = 0;

        foreach ($visits as $i => $visit) {
            [$category, $filename, $mime, $size] = $catalogue[$i % count($catalogue)];
            $when = Carbon::parse($visit->computed_at ?? Carbon::today()->subDays(20));

            PatientFile::create([
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'branch_id' => $visit->branch_id,
                'file_path' => 'patient-files/demo/'.$visit->patient_id.'/'.$when->format('Ymd').'-'.$filename,
                'original_filename' => $filename,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'category' => $category,
                'uploaded_by_user_id' => $uploader,
                'notes' => $category === 'lab_report' ? 'Results reviewed with the patient at the follow-up.' : null,
                'created_at' => $when,
                'updated_at' => $when,
            ]);
            $made++;
        }

        $this->command?->info("DemoOperationsSeeder: created {$made} patient files (metadata only, no blobs).");
    }

    // -------------------------------------------------------------------------

    /** Movements are stamped now() by the service; pull them back onto the visit date. */
    protected function backdateLastMovement($when): void
    {
        if (! $when) {
            return;
        }
        $at = $when instanceof Carbon ? $when : Carbon::parse($when);
        $id = ClinicStockMovement::query()->max('id');
        if ($id) {
            DB::table('clinic_stock_movements')->where('id', $id)->update(['created_at' => $at, 'updated_at' => $at]);
        }
    }

    protected function itemName(?ClinicItem $item): string
    {
        if (! $item) {
            return 'treatment';
        }
        $name = $item->name;
        if (is_array($name)) {
            return $name['en'] ?? reset($name) ?: 'treatment';
        }
        $decoded = is_string($name) && str_starts_with(trim($name), '{') ? json_decode($name, true) : null;

        return is_array($decoded) ? ($decoded['en'] ?? reset($decoded)) : (string) $name;
    }
}
