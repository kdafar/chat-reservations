<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Support\VisitAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class VisitConsoleController extends Controller
{
    use VisitAuthorization;

    /**
     * Single-patient v2 console. Read-only first pass: header, tabs
     * (Overview / Items / Payments / Notes), side rail with patient
     * history. Primary action (Start / Complete / Discharge) routes
     * back to the existing Filament endpoints for now.
     */
    public function show(Request $request, Visit $visit): Response
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403, 'Not authorized to view visits.');
        // Reflect any promotions created/edited since the last change.
        if (! $this->visitIsTerminal($visit)) {
            $this->recomputeTotals($visit);
        }
        // `booking.requestedPackage` is eager-loaded (never lazy) so the "patient
        // requested this offer" banner costs no extra query per render.
        $visit->load(['patient', 'doctor', 'branch', 'room', 'visitItems.clinicItem', 'payments', 'visitPackages.package.items.clinicItem', 'booking.requestedPackage']);

        return Inertia::render('Visit/Console', [
            'visit' => $this->transformVisit($visit),
            'history' => $this->recentVisitsFor($visit),
        ]);
    }

    /**
     * JSON view of a visit — used by the VisitSheet modal on the patient
     * profile so the doctor can review and edit clinical notes without
     * navigating away from the patient context.
     */
    public function showJson(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403, 'Not authorized to view visits.');
        // Reflect any promotions created/edited since the last change.
        if (! $this->visitIsTerminal($visit)) {
            $this->recomputeTotals($visit);
        }
        // Same eager-load set as show() — transformVisit() reads
        // booking.requestedPackage and must never lazy-load it.
        $visit->load(['patient', 'doctor', 'branch', 'room', 'visitItems.clinicItem', 'payments', 'visitPackages.package.items.clinicItem', 'booking.requestedPackage']);

        return response()->json([
            'ok' => true,
            'visit' => $this->transformVisit($visit),
        ]);
    }

    /**
     * Inline-update a small set of text fields on the visit. Each field is
     * optional — only fields present in the request are written.
     */
    public function update(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to edit this visit.'], 403);
        }

        if (! $this->visitAcceptsClinicalEdits($visit)) {
            return response()->json([
                'ok' => false,
                'error' => $this->clinicalEditsRejectionReason($visit),
            ], 422);
        }

        $data = $request->validate([
            'chief_complaint' => 'nullable|string|max:5000',
            'history' => 'nullable|string|max:5000',
            'examination' => 'nullable|string|max:5000',
            'diagnosis' => 'nullable|string|max:5000',
            'prescriptions' => 'nullable|string|max:10000',
            'patient_instructions' => 'nullable|string|max:5000',
            'lab_requests' => 'nullable|string|max:5000',
            'sick_leave_days' => 'nullable|integer|min:0|max:90',
            'follow_up_date' => 'nullable|date_format:Y-m-d',
            'notes' => 'nullable|string|max:5000',
        ]);

        $update = array_intersect_key($data, array_flip([
            'chief_complaint', 'history', 'examination', 'diagnosis',
            'prescriptions', 'patient_instructions', 'lab_requests',
            'sick_leave_days', 'follow_up_date', 'notes',
        ]));

        $visit->update($update + ['updated_by_user_id' => auth()->id()]);

        return response()->json(['ok' => true]);
    }

    /**
     * Search clinic items the doctor can add to this visit. Scoped to the
     * visit's branch (or global rows where branch_id is null). Only active +
     * billable items appear.
     */
    public function clinicItems(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403, 'Not authorized to view visits.');
        $q = trim((string) $request->query('q', ''));
        $partnerId = $visit->branch?->partner_id; // catalog is clinic-owned

        $rows = \App\Models\ClinicItem::query()
            ->where('is_active', true)
            ->when($request->boolean('stockable'),
                // Request-stock flow: only stockable items, billable flag irrelevant.
                fn ($w) => $w->where('is_stockable', true),
                // Default (add-item flow): billable items only.
                fn ($w) => $w->where('is_billable', true)
            )
            // Clinic (partner) ownership + optional within-clinic branch override.
            ->when($partnerId, fn ($w) => $w->where(function ($w2) use ($partnerId) {
                $w2->where('partner_id', $partnerId)->orWhereNull('partner_id');
            }))
            ->when($visit->branch_id, fn ($w) => $w->where(function ($w2) use ($visit) {
                $w2->whereNull('branch_id')->orWhere('branch_id', $visit->branch_id);
            }))
            ->when(mb_strlen($q) >= 2, fn ($w) => $w->where('name', 'like', '%'.$q.'%'))
            ->orderBy('name')
            ->limit(60)
            ->get(['id', 'name', 'type', 'default_price', 'default_cost'])
            ->map(fn ($it) => [
                'id' => $it->id,
                'name' => $this->resolveName($it->name),
                'type' => $it->type,
                'price' => (float) ($it->default_price ?? 0),
                'cost' => (float) ($it->default_cost ?? 0),
            ]);

        return response()->json(['items' => $rows]);
    }

    /**
     * Search clinic packages (services) the doctor can apply to this
     * visit. Same branch scoping as clinic items. Each package returns
     * its bundled items so the picker can preview what will be added.
     */
    public function clinicPackages(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403, 'Not authorized to view visits.');
        $q = trim((string) $request->query('q', ''));

        $partnerId = $visit->branch?->partner_id; // catalog is clinic-owned

        $rows = \App\Models\ClinicPackage::query()
            ->where('is_active', true)
            ->when($partnerId, fn ($w) => $w->where(function ($w2) use ($partnerId) {
                $w2->where('partner_id', $partnerId)->orWhereNull('partner_id');
            }))
            ->when($visit->branch_id, fn ($w) => $w->where(function ($w2) use ($visit) {
                $w2->whereNull('branch_id')->orWhere('branch_id', $visit->branch_id);
            }))
            ->when(mb_strlen($q) >= 2, fn ($w) => $w->where('name', 'like', '%'.$q.'%'))
            ->orderBy('id', 'desc')
            ->limit(60)
            ->with(['items.clinicItem'])
            ->get(['id', 'branch_id', 'name', 'default_price', 'discount_price', 'offer_starts_at', 'offer_ends_at'])
            // Branch isolation: a package's components are resolved through the
            // (branch-scoped) ClinicItem relation, so a component outside the
            // user's branch/clinic loads as a null clinicItem. Hide any package
            // that isn't fully usable here rather than offering an empty/partial
            // bundle. Admins bypass the item scope, so they still see everything.
            ->filter(fn ($pkg) => $pkg->items->every(fn ($pi) => $pi->clinicItem !== null))
            ->values();

        // Advertised-offer pricing: surface any active time-bound promotion on
        // each package so the picker can show the deal price + an OFFER badge.
        $promoSvc = app(\App\Services\Clinic\ClinicPromotionService::class);
        $rows = $rows->map(function ($pkg) use ($promoSvc, $visit) {
            $price = (float) ($pkg->default_price ?? 0);

            // Same layering as reapplyPromotions(): the package's own offer
            // price first, then any promotion on top of the reduced price.
            $offerPerUnit = min((float) $pkg->savings_amount, $price);
            $perUnit = $promoSvc->discountForPackage($pkg, max(0, $price - $offerPerUnit), (int) $visit->branch_id);
            $promo = $perUnit > 0.0001 ? $promoSvc->bestPackagePromotion($pkg, (int) $visit->branch_id) : null;
            $perUnit += $offerPerUnit;

            return [
                'id' => $pkg->id,
                'name' => $this->resolveName($pkg->name),
                'price' => $price,
                'offer_price' => $pkg->has_discount ? $pkg->effective_price : null,
                'saves' => round($perUnit, 3),
                'save_percent' => $price > 0 ? (int) round(($perUnit / $price) * 100) : 0,
                'net_price' => round(max(0, $price - $perUnit), 3),
                'promo' => $promo ? [
                    'name' => $this->resolveName($promo->name),
                    'discount' => round($perUnit, 3),
                ] : null,
                'items' => $pkg->items->map(fn ($pi) => [
                    'clinic_item_id' => $pi->clinic_item_id,
                    'name' => $pi->clinicItem ? $this->resolveName($pi->clinicItem->name) : ('#'.$pi->clinic_item_id),
                    'qty_base' => (float) ($pi->qty_base ?? 0),
                ])->values(),
            ];
        });

        return response()->json(['packages' => $rows]);
    }

    /**
     * The doctor profile id for the current user (null for non-doctor staff).
     * Used to scope personal clinical phrases.
     */
    private function currentDoctorId(): ?int
    {
        $uid = (int) (auth()->id() ?? 0);
        if (! $uid) {
            return null;
        }

        return (int) (\App\Models\Doctor::query()->where('user_id', $uid)->value('id')) ?: null;
    }

    /**
     * Who may add/remove/adjust PACKAGES on a visit: the treating doctor (or
     * admin) AND reception — packages are billing bundles the front desk also
     * manages. (Individual clinical items stay doctor/admin via canOperateVisit.)
     */
    protected function canManageVisitPackages(Visit $visit): bool
    {
        return $this->canOperateVisit($visit) || $this->canCollectPayment($visit);
    }

    /**
     * Quick-phrase library for one clinical field. Returns the shared clinic
     * phrases plus this doctor's personal favourites, filtered to the current
     * UI locale (or locale-agnostic rows), most-used first.
     */
    public function phrases(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403, 'Not authorized to view visits.');

        $field = (string) $request->query('field', '');
        if (! in_array($field, \App\Models\ClinicalPhrase::FIELDS, true)) {
            return response()->json(['phrases' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $locale = app()->getLocale();
        $doctorId = $this->currentDoctorId();

        $rows = \App\Models\ClinicalPhrase::query()
            ->where('is_active', true)
            ->where('field', $field)
            ->where(fn ($w) => $w->whereNull('locale')->orWhere('locale', $locale))
            ->where(function ($w) use ($doctorId) {
                $w->where('scope', 'clinic');
                if ($doctorId) {
                    $w->orWhere(fn ($w2) => $w2->where('scope', 'doctor')->where('doctor_id', $doctorId));
                }
            })
            ->when(mb_strlen($q) >= 1, fn ($w) => $w->where(
                fn ($w2) => $w2->where('label', 'like', '%'.$q.'%')->orWhere('body', 'like', '%'.$q.'%')
            ))
            ->orderByDesc('usage_count')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->limit(60)
            ->get(['id', 'label', 'body', 'scope', 'doctor_id'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => $p->label,
                'body' => $p->body,
                'scope' => $p->scope,
                'mine' => $doctorId && (int) $p->doctor_id === (int) $doctorId,
            ]);

        return response()->json(['phrases' => $rows]);
    }

    /**
     * Save a new quick-phrase from the console — either to the shared clinic
     * library or to the doctor's personal favourites. Non-doctor staff can
     * only contribute shared phrases.
     */
    public function savePhrase(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to add phrases.'], 403);
        }

        $data = $request->validate([
            'field' => 'required|string|in:'.implode(',', \App\Models\ClinicalPhrase::FIELDS),
            'label' => 'required|string|max:191',
            'body' => 'required|string|max:5000',
            'scope' => 'required|in:clinic,doctor',
        ]);

        $doctorId = $this->currentDoctorId();
        // Personal scope requires a doctor profile; fall back to shared otherwise.
        if ($data['scope'] === 'doctor' && ! $doctorId) {
            $data['scope'] = 'clinic';
        }

        $phrase = \App\Models\ClinicalPhrase::create([
            'field' => $data['field'],
            'locale' => app()->getLocale(),
            'label' => $data['label'],
            'body' => $data['body'],
            'scope' => $data['scope'],
            'doctor_id' => $data['scope'] === 'doctor' ? $doctorId : null,
            'branch_id' => null,
            'is_active' => true,
        ]);

        return response()->json([
            'ok' => true,
            'phrase' => [
                'id' => $phrase->id,
                'label' => $phrase->label,
                'body' => $phrase->body,
                'scope' => $phrase->scope,
                'mine' => $phrase->scope === 'doctor',
            ],
        ]);
    }

    /** Bump a phrase's usage counter so popular phrases float to the top. */
    public function usePhrase(Request $request, Visit $visit, \App\Models\ClinicalPhrase $phrase): \Illuminate\Http\JsonResponse
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403);
        $phrase->increment('usage_count');

        return response()->json(['ok' => true]);
    }

    /**
     * Search the outpatient drug formulary for the prescription builder.
     * Branch-scoped (visit branch or global rows), most-used first.
     */
    public function medications(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403, 'Not authorized to view visits.');

        $q = trim((string) $request->query('q', ''));

        $rows = \App\Models\Medication::query()
            ->where('is_active', true)
            ->when($visit->branch_id, fn ($w) => $w->where(
                fn ($w2) => $w2->where('branch_id', $visit->branch_id)->orWhereNull('branch_id')
            ))
            ->when(mb_strlen($q) >= 1, fn ($w) => $w->where('name', 'like', '%'.$q.'%'))
            ->orderByDesc('usage_count')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(60)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'strength' => $m->strength,
                'form' => $m->form,
                'route' => $m->route,
                'dose' => $m->default_dose,
                'frequency' => $m->default_frequency,
                'duration' => $m->default_duration,
                'instructions' => $m->default_instructions,
                'label' => $m->display_label,
                'line' => $m->defaultLine(),
            ]);

        return response()->json(['medications' => $rows]);
    }

    /** Bump a medication's usage counter. */
    public function useMedication(Request $request, Visit $visit, \App\Models\Medication $medication): \Illuminate\Http\JsonResponse
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403);
        $medication->increment('usage_count');

        return response()->json(['ok' => true]);
    }

    /**
     * Search the lab-test catalog for the Lab Requests picker. Reuses the
     * existing lab module's lab_tests table. Branch-scoped.
     */
    public function labTests(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403, 'Not authorized to view visits.');

        $q = trim((string) $request->query('q', ''));

        $rows = \App\Models\Lab\LabTest::query()
            ->where('is_active', true)
            ->when($visit->branch_id, fn ($w) => $w->where(
                fn ($w2) => $w2->where('branch_id', $visit->branch_id)->orWhereNull('branch_id')
            ))
            ->when(mb_strlen($q) >= 1, fn ($w) => $w->where(
                fn ($w2) => $w2->where('name', 'like', '%'.$q.'%')->orWhere('code', 'like', '%'.$q.'%')
            ))
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'code', 'name', 'specimen_type'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $this->resolveName($t->name),
                'specimen' => $t->specimen_type,
            ]);

        return response()->json(['lab_tests' => $rows]);
    }

    /**
     * Apply a clinic package (service) to this visit. Mirrors the Filament
     * addPackagesAction: snapshots the package price into a VisitPackage row
     * and then runs the stock service to either issue the bundled items
     * immediately or create a pending stock request.
     */
    public function addPackage(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        // Packages are billing bundles — reception (and admin) may manage them too,
        // not only the treating doctor.
        if (! $this->canManageVisitPackages($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to add services to this visit.'], 403);
        }

        if (! $this->visitAcceptsClinicalEdits($visit)) {
            return response()->json([
                'ok' => false,
                'error' => $this->clinicalEditsRejectionReason($visit),
            ], 422);
        }

        $data = $request->validate([
            'clinic_package_id' => 'required|integer|exists:clinic_packages,id',
            'qty' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $pkgSvc = app(\App\Services\Clinic\VisitPackageService::class);
            $stockSvc = app(\App\Services\Clinic\VisitStockRequestService::class);

            $userId = (int) (auth()->id() ?? 0);
            $line = ['clinic_package_id' => (int) $data['clinic_package_id'], 'qty' => (float) $data['qty']];

            // 1) Persist the VisitPackage row (price snapshot).
            $pkgSvc->applyPackagesOnly($visit, [$line], $userId, $data['notes'] ?? null);

            // 1b) Time-bound package promotion: auto-fill the package's line
            //     discount (only when it isn't already discounted).
            $vp = \App\Models\VisitPackage::query()
                ->where('visit_id', $visit->id)
                ->where('clinic_package_id', (int) $data['clinic_package_id'])
                ->first();
            if ($vp && (float) ($vp->discount_amount ?? 0) <= 0 && ! $this->visitHasNonStackingCoupon($visit)) {
                $pkgModel = \App\Models\ClinicPackage::find((int) $data['clinic_package_id']);
                if ($pkgModel) {
                    $perUnit = app(\App\Services\Clinic\ClinicPromotionService::class)
                        ->discountForPackage($pkgModel, (float) $vp->unit_price_snapshot, (int) $visit->branch_id);
                    if ($perUnit > 0) {
                        $disc = round(min($perUnit * (float) $vp->qty, (float) $vp->line_total), 3);
                        $vp->forceFill(['discount_amount' => $disc, 'discount_source' => 'promo'])->save();
                    }
                }
            }

            // 2) Compute bundled item requirements and either issue stock
            //    immediately or open a pending stock request.
            $visit->refresh();
            $requirements = method_exists($pkgSvc, 'requirementsForVisit')
                ? $pkgSvc->requirementsForVisit($visit)
                : $pkgSvc->requirementsForPackages((int) $visit->branch_id, [$line]);

            $result = $stockSvc->issueOrRequestForVisit($visit, $requirements, $userId, $data['notes'] ?? null);

            $this->recomputeTotals($visit);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'mode' => $result['mode'] ?? null, // 'issued' | 'requested'
        ]);
    }

    /**
     * Remove a previously-added package from the visit. Filament leaves the
     * issued items in place so they're not undone retroactively; we mirror
     * that — only the VisitPackage line is removed.
     */
    public function deletePackage(Request $request, Visit $visit, \App\Models\VisitPackage $package): \Illuminate\Http\JsonResponse
    {
        if (! $this->canManageVisitPackages($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to remove services from this visit.'], 403);
        }

        if (! $this->visitAcceptsClinicalEdits($visit)) {
            return response()->json([
                'ok' => false,
                'error' => $this->clinicalEditsRejectionReason($visit),
            ], 422);
        }

        if ($package->visit_id !== $visit->id) {
            return response()->json(['ok' => false, 'error' => 'Package does not belong to this visit.'], 422);
        }

        // Consumables this package contributed to the visit's PENDING stock
        // request, captured before deletion so we can reverse exactly its share.
        $removedRequirements = app(\App\Services\Clinic\VisitPackageService::class)
            ->requirementsForVisitPackage($package);

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $package) {
            $package->delete();
            $this->recomputeTotals($visit);
        });

        // Reverse the removed package's consumables out of any pending stock
        // request. If that empties the request it is cancelled ("Package removed
        // from visit") so it no longer looks actionable on the pharmacy worklist.
        if ($removedRequirements) {
            try {
                app(\App\Services\Clinic\VisitStockRequestService::class)
                    ->reduceForVisit($visit, $removedRequirements, 'Package removed from visit');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Set/clear a package line's discount (and optionally its qty). Billing edit
     * — allowed at checkout (awaiting payment), just not on a closed visit.
     */
    public function updatePackage(Request $request, Visit $visit, \App\Models\VisitPackage $package): \Illuminate\Http\JsonResponse
    {
        if (! $this->canManageVisitPackages($visit) || $this->visitIsTerminal($visit)) {
            return response()->json(['ok' => false, 'error' => 'This visit can no longer be edited.'], 422);
        }
        if ($package->visit_id !== $visit->id) {
            return response()->json(['ok' => false, 'error' => 'Package does not belong to this visit.'], 422);
        }

        $data = $request->validate([
            'qty' => 'nullable|numeric|min:1',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $package, $data) {
            $qty = isset($data['qty']) ? (float) $data['qty'] : (float) $package->qty;
            $unit = (float) $package->unit_price_snapshot;
            $lineTotal = round($unit * $qty, 3);
            $discount = array_key_exists('discount_amount', $data) && $data['discount_amount'] !== null
                ? max(0.0, min((float) $data['discount_amount'], $lineTotal))
                : (float) ($package->discount_amount ?? 0);

            $fill = ['qty' => $qty, 'line_total' => $lineTotal, 'discount_amount' => $discount];
            if (array_key_exists('discount_amount', $data) && $data['discount_amount'] !== null) {
                $fill['discount_source'] = $discount > 0 ? 'manual' : null;
            }
            $package->forceFill($fill)->save();

            $this->recomputeTotals($visit);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * True when the visit has a coupon that may not combine with promotions.
     */
    protected function visitHasNonStackingCoupon(Visit $visit): bool
    {
        if (! $visit->coupon_id) {
            return false;
        }
        $c = \App\Models\ClinicCoupon::find($visit->coupon_id);

        return $c && ! $c->stacks_with_promotions;
    }

    /**
     * Set the visit-level manual discount (amount KWD or percent), or clear it.
     * Billing edit — allowed at checkout.
     */
    public function setDiscount(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit) || $this->visitIsTerminal($visit)) {
            return response()->json(['ok' => false, 'error' => 'This visit can no longer be edited.'], 422);
        }

        $data = $request->validate([
            'type' => 'required|in:none,amount,percent',
            'value' => 'nullable|numeric|min:0',
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $data) {
            $none = $data['type'] === 'none';
            $visit->forceFill([
                'discount_type' => $none ? null : $data['type'],
                'discount_value' => $none ? 0 : (float) ($data['value'] ?? 0),
            ]);
            // Clearing the manual discount with no coupon also zeroes the resolved total.
            if ($none && ! $visit->coupon_id) {
                $visit->discount_total = 0;
            }
            $visit->save();
            $this->recomputeTotals($visit);
        });

        $visit->refresh();

        return response()->json(['ok' => true, 'discount_total' => (float) $visit->discount_total]);
    }

    /**
     * Apply a clinic coupon code to the visit. Validates against the current
     * subtotal + branch and records a usage. Billing edit — allowed at checkout.
     */
    public function applyCoupon(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit) || $this->visitIsTerminal($visit)) {
            return response()->json(['ok' => false, 'error' => 'This visit can no longer be edited.'], 422);
        }

        $data = $request->validate(['code' => 'required|string|max:64']);
        $coupon = \App\Models\ClinicCoupon::query()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($data['code']))])
            ->first();

        if (! $coupon) {
            return response()->json(['ok' => false, 'error' => 'Coupon code not found.'], 422);
        }

        // Subtotal = lines net of per-line discounts, before the visit-level discount.
        $this->recomputeTotals($visit);
        $visit->refresh();
        $subtotal = (float) $visit->fees_total + (float) $visit->packages_price_total + (float) $visit->items_price_total;

        if ($reason = $coupon->rejectionReason($subtotal, (int) $visit->branch_id)) {
            return response()->json(['ok' => false, 'error' => $reason], 422);
        }

        // Stacking rule: a non-stacking coupon can't combine with promotion-
        // applied line discounts already on the visit.
        if (! $coupon->stacks_with_promotions) {
            $hasPromo = \App\Models\VisitItem::where('visit_id', $visit->id)->where('discount_source', 'promo')->exists()
                || \App\Models\VisitPackage::where('visit_id', $visit->id)->where('discount_source', 'promo')->exists();
            if ($hasPromo) {
                return response()->json(['ok' => false, 'error' => 'This coupon can\'t be combined with the promotions already applied to this visit.'], 422);
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $coupon) {
            if ($visit->coupon_id && (int) $visit->coupon_id !== (int) $coupon->id) {
                \App\Models\ClinicCoupon::where('id', $visit->coupon_id)->where('uses_count', '>', 0)->decrement('uses_count');
            }
            if ((int) $visit->coupon_id !== (int) $coupon->id) {
                $coupon->increment('uses_count');
            }
            $visit->forceFill(['coupon_id' => $coupon->id, 'coupon_code' => $coupon->code])->save();
            $this->recomputeTotals($visit);
        });

        $visit->refresh();

        return response()->json([
            'ok' => true,
            'coupon_code' => $coupon->code,
            'discount_total' => (float) $visit->discount_total,
        ]);
    }

    /** Remove an applied coupon from the visit (releases the usage). */
    public function removeCoupon(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit) || $this->visitIsTerminal($visit)) {
            return response()->json(['ok' => false, 'error' => 'This visit can no longer be edited.'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit) {
            if ($visit->coupon_id) {
                \App\Models\ClinicCoupon::where('id', $visit->coupon_id)->where('uses_count', '>', 0)->decrement('uses_count');
            }
            $visit->forceFill(['coupon_id' => null, 'coupon_code' => null]);
            if (! in_array($visit->discount_type, ['amount', 'percent'], true)) {
                $visit->discount_total = 0;
            }
            $visit->save();
            $this->recomputeTotals($visit);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Explain why a visit refused a clinical-edit attempt so the toast
     * is actionable instead of "422 Unprocessable".
     */
    protected function clinicalEditsRejectionReason(Visit $visit): string
    {
        if (! $this->visitIsCheckedIn($visit)) {
            return 'Patient has not been checked in yet.';
        }
        if ($this->visitIsTerminal($visit)) {
            return 'This visit is closed and cannot be edited.';
        }
        if ($visit->status === Visit::STATUS_AWAITING_PAYMENT) {
            return 'Visit is awaiting payment — clinical edits are locked. Use the full editor if you need to amend.';
        }
        if ($visit->status === Visit::STATUS_CREATED) {
            return 'Visit has not started yet — reception must check the patient in first.';
        }

        return 'This visit is not in a state that accepts edits.';
    }

    /**
     * ClinicItem.name is stored as a translation array; pull the current
     * locale (falling back to en, then ar, then the first available value).
     */
    protected function resolveName($raw): string
    {
        if (is_string($raw)) {
            return $raw;
        }
        if (! is_array($raw)) {
            return '';
        }
        $locale = app()->getLocale();

        return (string) ($raw[$locale] ?? $raw['en'] ?? $raw['ar'] ?? reset($raw) ?? '');
    }

    /**
     * Add a clinic item to the visit. Snapshots cost + price so future
     * catalog edits don't rewrite the historical line. Then re-runs the
     * costing service so the totals card refreshes immediately.
     */
    public function addItem(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to add items to this visit.'], 403);
        }

        if (! $this->visitAcceptsClinicalEdits($visit)) {
            return response()->json([
                'ok' => false,
                'error' => $this->clinicalEditsRejectionReason($visit),
            ], 422);
        }

        $data = $request->validate([
            'clinic_item_id' => 'required|integer|exists:clinic_items,id',
            'qty' => 'required|numeric|min:0.001',
            'unit_price' => 'nullable|numeric|min:0',
        ]);

        $clinicItem = \App\Models\ClinicItem::findOrFail($data['clinic_item_id']);

        $unitPrice = $data['unit_price'] ?? (float) ($clinicItem->default_price ?? 0);
        $unitCost = (float) ($clinicItem->default_cost ?? 0);

        // Fold a service's bill-of-materials cost into its unit cost so visit
        // profit reflects the consumables it uses (not just the service's own
        // default cost). The catalog item is never mutated — this is a snapshot.
        if ($clinicItem->isService()) {
            $unitCost = round($unitCost + app(\App\Services\Clinic\ServiceBomService::class)->materialCost($clinicItem->id), 3);
        }

        // Time-bound catalog promotion: auto-fill the line discount so staff
        // don't discount each item by hand. Only applied when the price wasn't
        // overridden (a manual unit_price means the operator set their own deal).
        // ...unless a non-stacking coupon is on the visit (then the coupon wins
        // and the line promo is skipped).
        $lineDiscount = 0.0;
        if ((! isset($data['unit_price']) || $data['unit_price'] === null) && ! $this->visitHasNonStackingCoupon($visit)) {
            $perUnit = app(\App\Services\Clinic\ClinicPromotionService::class)
                ->discountForItem($clinicItem, $unitPrice, (int) $visit->branch_id);
            $lineDiscount = round(min($perUnit * (float) $data['qty'], $unitPrice * (float) $data['qty']), 3);
        }
        $discSource = $lineDiscount > 0 ? 'promo' : null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $clinicItem, $data, $unitPrice, $unitCost, $lineDiscount, $discSource) {
            \App\Models\VisitItem::create([
                'visit_id' => $visit->id,
                'clinic_item_id' => $clinicItem->id,
                'branch_id' => $visit->branch_id,
                'qty' => $data['qty'],
                'unit_cost_snapshot' => $unitCost,
                'unit_price_snapshot' => $unitPrice,
                'line_cost_total' => round($unitCost * $data['qty'], 3),
                'line_price_total' => round($unitPrice * $data['qty'], 3),
                'discount_amount' => $lineDiscount,
                'discount_source' => $discSource,
            ]);

            $this->recomputeTotals($visit);
        });

        // Auto-deduct stock for what was added (service BOM, or a stockable
        // product/consumable). Guarded against items already on a pending stock
        // request so manual dispensing isn't double-counted. Never blocks the add.
        $mode = $this->autoDeductStock($visit, $clinicItem, (float) $data['qty']);

        return response()->json(['ok' => true, 'stock_mode' => $mode]);
    }

    /**
     * Update qty / unit_price on an existing VisitItem — snapshots only,
     * never touches the catalog.
     */
    public function updateItem(Request $request, Visit $visit, \App\Models\VisitItem $item): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to edit items on this visit.'], 403);
        }

        if (! $this->visitAcceptsClinicalEdits($visit)) {
            return response()->json([
                'ok' => false,
                'error' => $this->clinicalEditsRejectionReason($visit),
            ], 422);
        }

        if ($item->visit_id !== $visit->id) {
            return response()->json(['ok' => false, 'error' => 'Item does not belong to this visit.'], 422);
        }

        $data = $request->validate([
            'qty' => 'nullable|numeric|min:0.001',
            'unit_price' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        $oldQty = (float) $item->qty;

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $item, $data) {
            $qty = isset($data['qty']) ? (float) $data['qty'] : (float) $item->qty;
            $price = isset($data['unit_price']) ? (float) $data['unit_price'] : (float) $item->unit_price_snapshot;
            $lineTotal = round($price * $qty, 3);

            // Per-line discount: cap at the line total so we never push the
            // line negative regardless of what the doctor types.
            $rawDiscount = array_key_exists('discount_amount', $data) && $data['discount_amount'] !== null
                ? (float) $data['discount_amount']
                : (float) ($item->discount_amount ?? 0);
            $discount = max(0, min($rawDiscount, $lineTotal));

            $fill = [
                'qty' => $qty,
                'unit_price_snapshot' => $price,
                'line_price_total' => $lineTotal,
                'line_cost_total' => round((float) $item->unit_cost_snapshot * $qty, 3),
                'discount_amount' => $discount,
            ];
            // A staff edit of the discount marks the line as a manual discount.
            if (array_key_exists('discount_amount', $data) && $data['discount_amount'] !== null) {
                $fill['discount_source'] = $discount > 0 ? 'manual' : null;
            }
            $item->forceFill($fill)->save();

            $this->recomputeTotals($visit);
        });

        // If the qty INCREASED, deduct the extra (service BOM or stockable item).
        // A decrease/removal is intentionally NOT auto-reversed — stock already
        // issued may be physically used, mirroring how package removal leaves
        // issued items in place; correct mistakes with a manual stock adjustment.
        $delta = (float) $item->qty - $oldQty;
        if ($delta > 0) {
            $clinicItem = \App\Models\ClinicItem::find($item->clinic_item_id);
            if ($clinicItem) {
                $qtyNote = '(qty +'.rtrim(rtrim(number_format($delta, 4, '.', ''), '0'), '.').')';
                $this->autoDeductStock($visit, $clinicItem, $delta, $qtyNote);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Auto-deduct stock implied by adding/growing an item on a visit: a service
     * explodes into its bill-of-materials consumables; a stockable product or
     * consumable deducts itself; non-stockable items deduct nothing. Stock is
     * issued now, or a pending request is opened when short.
     *
     * Guard: any component that already has an OPEN (pending) stock request on
     * the visit is skipped, so an item being dispensed via the manual stock
     * flow is never deducted twice. Non-blocking — failures are logged, not thrown.
     *
     * @return string|null issue mode ('issued'|'request'|'disabled') or null when nothing was deducted
     */
    private function autoDeductStock(Visit $visit, \App\Models\ClinicItem $clinicItem, float $qty, ?string $qtyNote = null): ?string
    {
        try {
            $requirements = app(\App\Services\Clinic\ServiceBomService::class)
                ->requirementsForItem($clinicItem->id, $qty);
            if (! $requirements) {
                return null;
            }

            $stockSvc = app(\App\Services\Clinic\VisitStockRequestService::class);

            $pending = $stockSvc->pendingItemIds($visit);
            if ($pending) {
                $requirements = array_values(array_filter(
                    $requirements,
                    fn ($r) => ! in_array((int) $r['clinic_item_id'], $pending, true),
                ));
            }
            if (! $requirements) {
                return null;
            }

            $label = ($clinicItem->isService() ? 'Service BOM' : 'Item used').($qtyNote ? ' '.$qtyNote : '');
            $visit->refresh();
            $result = $stockSvc->issueOrRequestForVisit(
                $visit,
                $requirements,
                (int) (auth()->id() ?? 0),
                $label.': '.$clinicItem->localized_name,
            );

            return $result['mode'] ?? null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Remove a VisitItem from this visit and recompute totals. */
    public function deleteItem(Request $request, Visit $visit, \App\Models\VisitItem $item): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to remove items from this visit.'], 403);
        }

        if (! $this->visitAcceptsClinicalEdits($visit)) {
            return response()->json([
                'ok' => false,
                'error' => $this->clinicalEditsRejectionReason($visit),
            ], 422);
        }

        if ($item->visit_id !== $visit->id) {
            return response()->json(['ok' => false, 'error' => 'Item does not belong to this visit.'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $item) {
            $item->delete();
            $this->recomputeTotals($visit);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Record a payment against this visit. Reception/admin can collect cash,
     * card, K-Net, link or transfer payments without leaving the console.
     * Totals are recomputed after the row is persisted.
     */
    public function addPayment(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to collect payments.'], 403);
        }

        if (! $this->visitAcceptsPayments($visit)) {
            return response()->json([
                'ok' => false,
                'error' => ! $this->visitIsCheckedIn($visit)
                    ? 'Patient has not been checked in yet — cannot record a payment.'
                    : 'This visit is closed — payments cannot be recorded.',
            ], 422);
        }

        // Methods are admin-configurable per clinic/branch. Validate against the
        // resolved set (falling back to the manual POS defaults if a clinic has
        // none configured) instead of a hard-coded enum.
        $methods = app(\App\Services\Clinic\ClinicPaymentMethodResolver::class)
            ->forBranch((int) $visit->branch_id, (int) ($visit->branch?->partner_id ?? 0));
        $allowedKeys = array_values(array_filter(array_column($methods, 'key')));
        if (empty($allowedKeys)) {
            $allowedKeys = ['cash', 'card', 'knet', 'transfer', 'insurance'];
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.001',
            // 'insurance' records an insurer-paid portion → posts to the insurance
            // receivable (1110), not bank. The richer per-kind coverage flow is the
            // dedicated insurance claim; this is the simple manual-entry parity.
            'method' => ['required', \Illuminate\Validation\Rule::in($allowedKeys)],
            // Canonical kind enum — must match VisitPayment / accounting
            // (ChartOfAccounts::revenueAccountFor) and insurance CoverageCalculator.
            // 'services' = packages, 'medicines' = items/consumables. Using
            // 'items'/'packages' here would mis-post revenue to 4010 (consultation).
            'kind' => 'required|in:consultation,services,medicines,other',
            'reference_no' => 'nullable|string|max:64',
        ]);

        // Card / KNET / transfer / online require a transaction/reference id —
        // cash doesn't. Enforce server-side per the method's config flag.
        $chosen = collect($methods)->firstWhere('key', $data['method']);
        if (($chosen['requires_reference'] ?? false) && trim((string) ($data['reference_no'] ?? '')) === '') {
            return response()->json([
                'ok' => false,
                'error' => 'A transaction / reference number is required for '.($chosen['label'] ?? $data['method']).' payments.',
                'field' => 'reference_no',
            ], 422);
        }

        // Never collect more than the visit owes. Without this an operator
        // typo (270 for 27) is accepted in full and the visit sits overpaid.
        $balance = app(\App\Services\Clinic\VisitBalanceService::class);
        if ($reason = $balance->rejectPayment($visit, (float) $data['amount'])) {
            return response()->json([
                'ok' => false,
                'error' => $reason,
                'field' => 'amount',
                'outstanding' => $balance->outstanding($visit),
            ], 422);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $data, $balance) {
                // Re-check under a row lock: two receptionists collecting at the
                // same moment would each pass the check above and together
                // overshoot the balance.
                $locked = Visit::query()->lockForUpdate()->findOrFail($visit->id);
                if ($reason = $balance->rejectPayment($locked, (float) $data['amount'])) {
                    throw new \RuntimeException($reason);
                }

                VisitPayment::create([
                    'visit_id' => $visit->id,
                    'amount' => $data['amount'],
                    'method' => $data['method'],
                    'kind' => $data['kind'],
                    'reference_no' => $data['reference_no'] ?? null,
                    'status' => 'paid',
                    'collected_by_user_id' => auth()->id(),
                    'paid_at' => now(),
                ]);

                $this->recomputeTotals($visit);
            });
        } catch (\RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'field' => 'amount',
                'outstanding' => $balance->outstanding($visit->refresh()),
            ], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Generate a MyFatoorah payment link for this visit's outstanding balance
     * (or an explicit amount) and return it plus a scannable QR. The actual
     * VisitPayment is recorded by the callback once MyFatoorah confirms the
     * charge — never here — so this is safe to call repeatedly.
     */
    public function createPaymentLink(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to collect payments.'], 403);
        }
        if (! $this->visitAcceptsPayments($visit)) {
            return response()->json(['ok' => false, 'error' => 'This visit cannot accept payments right now.'], 422);
        }

        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.001',
            'kind' => 'nullable|in:consultation,services,medicines,other',
        ]);

        try {
            $res = app(\App\Services\Clinic\VisitPaymentLinkService::class)->createForVisit(
                $visit,
                isset($data['amount']) ? (float) $data['amount'] : null,
                $data['kind'] ?? 'other',
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $res);
    }

    /**
     * Generate a payment link and push it to the patient's WhatsApp number.
     *
     * Prefers the approved UTILITY template (`clinic_payment_link`) — Meta
     * exempts approved templates from the 24-hour customer-service window, so
     * this delivers even to patients who haven't messaged us recently. If the
     * template isn't approved yet we fall back to a plain session message
     * (only valid inside 24h); outside the window Meta blocks it and we surface
     * a soft error so reception can copy/scan the link instead.
     */
    public function sendPaymentLinkWhatsApp(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to collect payments.'], 403);
        }
        // Same payable-state gate as createPaymentLink — never push a link for a
        // visit that's closed / not yet checked in.
        if (! $this->visitAcceptsPayments($visit)) {
            return response()->json(['ok' => false, 'error' => 'This visit cannot accept payments right now.'], 422);
        }

        $phone = (string) ($visit->patient?->phone ?? $visit->booking?->msisdn ?? '');
        if (trim($phone) === '') {
            return response()->json(['ok' => false, 'error' => 'No phone number on file for this patient.'], 422);
        }

        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.001',
            'kind' => 'nullable|in:consultation,services,medicines,other',
        ]);

        try {
            // Always mint the link server-side for THIS visit — never accept a URL
            // from the client (which could be arbitrary or a stale/expired link).
            $res = app(\App\Services\Clinic\VisitPaymentLinkService::class)->createForVisit(
                $visit,
                isset($data['amount']) ? (float) $data['amount'] : null,
                $data['kind'] ?? 'other',
            );
            $url = $res['url'];
            $amount = (float) $res['amount'];

            $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
            $wa = app(\App\Wa\Services\WhatsApp\WhatsAppService::class);

            if ($this->paymentTemplateApproved($locale)) {
                // Template path — works inside AND outside the 24-hour window.
                $name = $visit->patient?->name ?: ($locale === 'ar' ? 'عميلنا' : 'there');
                $clinic = $visit->branch?->getTranslation('name', $locale, true)
                    ?: config('app.name', 'Our Clinic');
                $appointment = $this->paymentApptText($visit, $locale);
                $amountText = $locale === 'ar'
                    ? number_format($amount, 3).' د.ك'
                    : number_format($amount, 3).' KWD';

                $wa->sendClinicPaymentLink($phone, $locale, $name, $clinic, $appointment, $amountText, $url);

                return response()->json(['ok' => true, 'via' => 'template']);
            }

            // Fallback: plain session message (only valid inside the 24h window).
            $name = $visit->patient?->name ?: '';
            $msg = $locale === 'ar'
                ? trim("مرحباً {$name}، يرجى إتمام الدفع".($amount ? ' بمبلغ '.number_format($amount, 3).' د.ك' : '').' عبر الرابط: '.$url)
                : trim("Hello {$name}, please complete your payment".($amount ? ' of '.number_format($amount, 3).' KWD' : '').' here: '.$url);

            $sent = $wa->sendTextMessage($phone, $msg);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        if (! $sent) {
            return response()->json([
                'ok' => false,
                'soft' => true,
                'error' => 'Could not send on WhatsApp (patient may be outside the 24-hour window, and the payment-link template is not approved yet). The link is still available to copy or scan.',
            ], 422);
        }

        return response()->json(['ok' => true, 'via' => 'text']);
    }

    /**
     * Is the payment-link template approved on Meta for this language? (We mirror
     * Meta status into the local wa.message_templates table on sync.) Only then
     * can we send outside the 24-hour window.
     */
    private function paymentTemplateApproved(string $locale): bool
    {
        $name = config('services.whatsapp.templates.payment_link', 'clinic_payment_link');

        try {
            return \App\Wa\Hub\Models\MessageTemplate::query()
                ->where('name', $name)
                ->where('status', 'APPROVED')
                ->where(fn ($q) => $q->where('language', $locale)->orWhereNull('language'))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Human, locale-aware "when" string for the payment template's {{3}} slot.
     * Prefers the booking's reserved date/time, then check-in, then created_at —
     * we never return an empty string (Meta rejects empty template params).
     */
    private function paymentApptText(Visit $visit, string $locale): string
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $dt = null;

        $b = $visit->booking;
        if ($b && trim((string) $b->res_date) !== '') {
            try {
                $date = str_replace('/', '-', \Illuminate\Support\Str::of((string) $b->res_date)->before(' ')->value());
                $time = trim((string) $b->res_time) !== '' ? trim((string) $b->res_time) : '00:00';
                $dt = \Carbon\Carbon::parse("{$date} {$time}", $tz);
            } catch (\Throwable $e) {
                $dt = null;
            }
        }

        $dt = $dt ?? $visit->checked_in_at ?? $visit->created_at;
        if (! $dt) {
            return $locale === 'ar' ? 'زيارتك' : 'your visit';
        }

        $dt = \Carbon\Carbon::parse($dt)->setTimezone($tz)->locale($locale);

        return $locale === 'ar'
            ? $dt->isoFormat('dddd D MMMM، h:mm a')
            : $dt->isoFormat('ddd, MMM D [at] h:mm A');
    }

    /**
     * Insurance coverage estimate for this visit, by kind — what the insurer
     * would pay per consultation/services/medicines. Powers the "Apply
     * insurance" action so reception can record the insurer portions in one go
     * (the heavier full claim lives in the dedicated insurance module).
     */
    /**
     * Insurer + plan options for the reception "capture insurance" form, plus
     * the patient's civil id (if already on file). Lets reception attach a
     * policy on the spot from the visit modal.
     */
    public function insuranceOptions(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'Not authorised.'], 403);
        }

        $insurers = \App\Models\Insurance\Insurer::query()
            ->where('is_active', true)
            ->with(['plans' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'plans' => $i->plans->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            ])->values();

        return response()->json([
            'ok' => true,
            'insurers' => $insurers,
            'civil_id' => $visit->patient?->civil_id,
        ]);
    }

    /**
     * Reception captures a patient's insurance from the visit modal: civil id +
     * insurer/plan/policy number → creates a PatientInsurancePolicy (first one
     * becomes primary) and stores the civil id on the patient. Clears any prior
     * "skip claim" stamp so the now-insured visit re-prompts for a claim.
     */
    public function attachInsurance(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'Not authorised.'], 403);
        }
        if (! $visit->patient_id) {
            return response()->json(['ok' => false, 'error' => 'This visit has no patient on file.'], 422);
        }

        $data = $request->validate([
            'civil_id' => 'nullable|string|max:32',
            // Only ACTIVE insurer/plan rows may be attached — the picker only
            // shows active ones, but validate it server-side too (stale/crafted).
            'insurer_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('insurers', 'id')->where('is_active', true)],
            'plan_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('insurance_plans', 'id')->where('is_active', true)],
            'policy_number' => 'required|string|max:100',
            'member_id' => 'nullable|string|max:100',
            'card_number' => 'nullable|string|max:100',
        ]);

        // Plan must belong to the chosen insurer (defends against a stale/crafted form).
        $planInsurerId = (int) \App\Models\Insurance\InsurancePlan::whereKey($data['plan_id'])->value('insurer_id');
        if ($planInsurerId !== (int) $data['insurer_id']) {
            return response()->json(['ok' => false, 'error' => 'Selected plan does not belong to that insurer.'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $data) {
            $patient = $visit->patient;
            if (! empty($data['civil_id'])) {
                $patient->forceFill(['civil_id' => $data['civil_id']])->save();
            }

            $hasPrimary = \App\Models\Insurance\PatientInsurancePolicy::query()
                ->where('patient_id', $patient->id)->where('is_primary', true)->exists();

            \App\Models\Insurance\PatientInsurancePolicy::create([
                'patient_id' => $patient->id,
                'insurer_id' => $data['insurer_id'],
                'plan_id' => $data['plan_id'],
                'policy_number' => $data['policy_number'],
                'member_id' => $data['member_id'] ?? null,
                'card_number' => $data['card_number'] ?? null,
                'status' => 'active',
                'is_primary' => ! $hasPrimary,
                'priority' => 1,
            ]);

            // Re-open the insurance decision for this visit if it was skipped.
            if (! empty($visit->insurance_claim_skipped_at)) {
                $visit->forceFill([
                    'insurance_claim_skipped_at' => null,
                    'insurance_claim_skip_reason' => null,
                ])->save();
            }
        });

        return response()->json(['ok' => true]);
    }

    public function estimateInsurance(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to apply insurance.'], 403);
        }

        $svc = app(\App\Services\Insurance\InsuranceService::class);
        $primary = $visit->patient ? $svc->primaryPolicyFor($visit->patient) : null;
        $estimate = $svc->estimateForVisit($visit);

        $applied = VisitPayment::query()
            ->where('visit_id', $visit->id)
            ->where('method', 'insurance')
            ->whereIn('status', ['paid', 'pending'])
            ->pluck('kind')->all();

        $kinds = [];
        foreach (($estimate['by_kind'] ?? []) as $kind => $bucket) {
            $insurer = round((float) array_sum(array_column($bucket['insurer_portions'] ?? [], 'amount')), 3);
            if ($insurer <= 0) {
                continue;
            }
            $kinds[] = [
                'kind' => $kind,
                'insurer_amount' => $insurer,
                'already_applied' => in_array($kind, $applied, true),
            ];
        }

        return response()->json([
            'ok' => true,
            'has_policy' => (bool) $primary,
            'policy' => $primary ? [
                'id' => $primary->id,
                'insurer' => $primary->insurer?->name,
                'plan' => $primary->plan?->name,
            ] : null,
            'kinds' => $kinds,
            'totals' => $estimate['totals'] ?? ['gross' => 0, 'patient_total' => 0, 'insurer_total' => 0],
        ]);
    }

    /**
     * Record insurer-paid portions for the selected kinds (method=insurance →
     * posts to insurance receivable). Mirrors the old Filament applyInsurance
     * action: skips kinds already covered, then recomputes totals.
     */
    public function applyInsurance(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to apply insurance.'], 403);
        }
        if (! $this->visitAcceptsPayments($visit)) {
            return response()->json(['ok' => false, 'error' => 'This visit is closed — payments cannot be recorded.'], 422);
        }

        $data = $request->validate([
            'kinds' => ['required', 'array', 'min:1'],
            'kinds.*' => ['string', 'in:consultation,services,medicines,other'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $svc = app(\App\Services\Insurance\InsuranceService::class);
        $primary = $visit->patient ? $svc->primaryPolicyFor($visit->patient) : null;
        if (! $primary) {
            return response()->json(['ok' => false, 'error' => 'No active insurance policy for this patient.'], 422);
        }
        $byKind = $svc->estimateForVisit($visit)['by_kind'] ?? [];

        $created = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $data, $byKind, $primary, &$created) {
            foreach ($data['kinds'] as $kind) {
                $bucket = $byKind[$kind] ?? null;
                if (! $bucket) {
                    continue;
                }
                $amount = round((float) array_sum(array_column($bucket['insurer_portions'] ?? [], 'amount')), 3);
                if ($amount <= 0) {
                    continue;
                }
                // Don't double-record an insurer portion for the same kind.
                $exists = VisitPayment::query()
                    ->where('visit_id', $visit->id)->where('kind', $kind)
                    ->where('method', 'insurance')->whereIn('status', ['paid', 'pending'])->exists();
                if ($exists) {
                    continue;
                }
                VisitPayment::create([
                    'visit_id' => $visit->id,
                    'amount' => $amount,
                    'method' => 'insurance',
                    'kind' => $kind,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'collected_by_user_id' => auth()->id(),
                    'meta' => ['insurance' => [
                        'policy_id' => $primary->id,
                        'insurer_id' => $primary->insurer_id,
                        'plan_id' => $primary->plan_id,
                        'note' => $data['note'] ?? null,
                    ]],
                ]);
                $created++;
            }
            $this->recomputeTotals($visit);
        });

        return response()->json(['ok' => true, 'created' => $created]);
    }

    /**
     * Void a previously recorded payment. Only the original collector or an
     * admin can void, and only paid (non-voided) rows are eligible. Uses the
     * model's SoftDeletes so the row stays around for audit/accounting.
     */
    public function voidPayment(Request $request, Visit $visit, VisitPayment $payment): \Illuminate\Http\JsonResponse
    {
        if ($payment->visit_id !== $visit->id) {
            return response()->json(['ok' => false, 'error' => 'Payment does not belong to this visit.'], 422);
        }

        $isCollector = (int) $payment->collected_by_user_id === (int) (auth()->id() ?? 0);

        if (! $this->isAdminUser() && ! $isCollector) {
            return response()->json(['ok' => false, 'error' => 'You are not allowed to void this payment.'], 403);
        }

        if ($payment->status !== 'paid') {
            return response()->json(['ok' => false, 'error' => 'Only paid payments can be voided.'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $payment) {
            // Flip the status rather than delete the row. VisitPaymentAccounting-
            // Observer reverses the journal entry on a status change to 'void';
            // a delete fires no `updated` event, so the cash/AR entry survived a
            // void and the ledger kept money the visit no longer counted. Every
            // balance query filters on status='paid', so the voided row drops out
            // of the totals while staying visible (and auditable) on the visit.
            $payment->forceFill([
                'status' => 'void',
                'meta' => array_merge((array) ($payment->meta ?? []), [
                    'void' => [
                        'at' => now()->toIso8601String(),
                        'by_user_id' => auth()->id(),
                    ],
                ]),
            ])->save();

            $this->recomputeTotals($visit);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Defer to the existing VisitCostingService when the feature flag is
     * enabled; otherwise keep items_*_total snapshots consistent so the UI
     * still shows accurate numbers.
     */
    /**
     * Refresh auto (promotion) line discounts on the visit from the CURRENTLY
     * active promotions, so a promotion created/edited after items were added is
     * reflected accurately. Only touches lines whose discount_source is not
     * 'manual' (manual discounts are never overwritten). A line that no longer
     * matches any promotion has its promo discount cleared.
     */
    protected function reapplyPromotions(Visit $visit): void
    {
        if ($this->visitIsTerminal($visit)) {
            return;
        }
        // Promotions don't stack with a non-stacking coupon — mirror addItem.
        if ($this->visitHasNonStackingCoupon($visit)) {
            return;
        }

        try {
            $promo = app(\App\Services\Clinic\ClinicPromotionService::class);
            $branchId = (int) ($visit->branch_id ?? 0);

            foreach ($visit->visitItems()->get() as $vi) {
                if ($vi->discount_source === 'manual') {
                    continue;
                }
                $item = \App\Models\ClinicItem::query()->find($vi->clinic_item_id);
                if (! $item) {
                    continue;
                }
                $unit = (float) ($vi->unit_price_snapshot ?? 0);
                $perUnit = $promo->discountForItem($item, $unit, $branchId);
                $disc = round(min($perUnit * (float) $vi->qty, (float) $vi->line_price_total), 3);
                if ((float) ($vi->discount_amount ?? 0) !== $disc || ($disc > 0 && $vi->discount_source !== 'promo')) {
                    $vi->forceFill(['discount_amount' => $disc, 'discount_source' => $disc > 0 ? 'promo' : null])->save();
                }
            }

            foreach ($visit->visitPackages()->get() as $vp) {
                if ($vp->discount_source === 'manual') {
                    continue;
                }
                $pkg = \App\Models\ClinicPackage::query()->find($vp->clinic_package_id);
                if (! $pkg) {
                    continue;
                }
                // Two discounts can apply to a package line and they layer in a
                // fixed order: the package's own offer price comes off the main
                // price first, then any time-bound promotion is calculated
                // against that already-reduced price (never against the main
                // price, which would give the deal away twice).
                $unit = (float) ($vp->unit_price_snapshot ?? 0);
                $offerPerUnit = min((float) $pkg->savings_amount, $unit);
                $promoPerUnit = $promo->discountForPackage($pkg, max(0, $unit - $offerPerUnit), $branchId);
                $perUnit = $offerPerUnit + $promoPerUnit;

                $disc = round(min($perUnit * (float) $vp->qty, (float) $vp->line_total), 3);
                $source = $promoPerUnit > 0 ? 'promo' : ($offerPerUnit > 0 ? 'offer' : null);

                if ((float) ($vp->discount_amount ?? 0) !== $disc || ($disc > 0 && $vp->discount_source !== $source)) {
                    $vp->forceFill(['discount_amount' => $disc, 'discount_source' => $source])->save();
                }
            }
        } catch (\Throwable $e) {
            report($e); // never block the costing path
        }
    }

    protected function recomputeTotals(Visit $visit): void
    {
        // Keep promotion line-discounts current before re-totalling.
        $this->reapplyPromotions($visit);

        try {
            if (config('clinic.visit_financials_enabled', false)) {
                app(\App\Services\Clinic\VisitCostingService::class)->compute($visit, (int) (auth()->id() ?? 0));

                return;
            }
        } catch (\Throwable $e) {
            // fall through to manual
        }

        $items = $visit->visitItems()->get();
        $visit->forceFill([
            'items_cost_total' => round((float) $items->sum('line_cost_total'), 3),
            'items_price_total' => round((float) $items->sum('line_price_total'), 3),
        ])->save();
    }

    /**
     * Doctor accepts the patient — flips status awaiting_doctor → in_progress,
     * stamps service_started_at + accepted_at. Mirrors the Filament flow.
     */
    public function start(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to start this visit.'], 403);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($visit) {
                /** @var Visit $fresh */
                $fresh = Visit::query()->lockForUpdate()->findOrFail($visit->id);

                if (! in_array($fresh->status, [Visit::STATUS_AWAITING_DOCTOR, Visit::STATUS_AWAITING_STOCK], true)) {
                    throw new \RuntimeException('Visit is not in a state that can be started.');
                }

                // Patient must be physically checked in before treatment can begin.
                if (empty($fresh->checked_in_at)) {
                    throw new \RuntimeException('Patient has not been checked in yet.');
                }

                // Already accepted by someone else (race-condition guard). Keyed
                // on accepted_by_user_id, not accepted_at: a stale accepted_at
                // with no owner (a visit pushed back to the queue, or seeded
                // data) is not a claim by another doctor and must not block.
                if ($fresh->accepted_by_user_id && (int) $fresh->accepted_by_user_id !== (int) (auth()->id() ?? 0)) {
                    throw new \RuntimeException('This visit has already been accepted by another doctor.');
                }

                // Real-life rule: a doctor handles ONE patient at a time.
                // They may start a second visit only if their current one is
                // awaiting_stock (the first patient is parked, waiting for
                // supplies). Otherwise refuse. Admin bypasses this check.
                if (! $this->isAdminUser() && $fresh->doctor_id) {
                    $busyVisit = Visit::query()
                        ->where('doctor_id', $fresh->doctor_id)
                        ->where('status', Visit::STATUS_IN_PROGRESS)
                        ->where('id', '!=', $fresh->id)
                        ->first(['id', 'booking_code']);

                    if ($busyVisit) {
                        throw new \RuntimeException(
                            'You already have a patient in treatment'
                            .($busyVisit->booking_code ? ' ('.$busyVisit->booking_code.')' : ' (visit #'.$busyVisit->id.')')
                            .'. Complete that visit, or move it to "Awaiting stock", before accepting another patient.'
                        );
                    }
                }

                $now = now();
                $fresh->status = Visit::STATUS_IN_PROGRESS;
                $fresh->service_started_at = $fresh->service_started_at ?? $now;
                // An unowned accepted_at is stale — this acceptance is the real
                // one, so stamp both together and keep them consistent.
                $fresh->accepted_at = $fresh->accepted_by_user_id ? $fresh->accepted_at : $now;
                $fresh->accepted_by_user_id = $fresh->accepted_by_user_id ?? auth()->id();
                $fresh->updated_by_user_id = auth()->id();
                $fresh->save();
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Doctor finishes treatment — flips in_progress → awaiting_payment and
     * frees the room. Mirrors Filament WaitingPatients::completeVisitAction
     * exactly: only the status changes; `completed_at` stays NULL until
     * reception discharges the patient.
     */
    public function complete(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to complete this visit.'], 403);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($visit) {
                /** @var Visit $fresh */
                $fresh = Visit::query()->lockForUpdate()->with('pendingStockRequest')->findOrFail($visit->id);

                if ($fresh->status !== Visit::STATUS_IN_PROGRESS) {
                    throw new \RuntimeException('Only an in-progress visit can be completed.');
                }

                // Mirrors Filament: cannot leave the room while stock is pending.
                if ($fresh->pendingStockRequest) {
                    throw new \RuntimeException('Cannot finish: there is a pending stock request. Fulfil or cancel it first.');
                }

                if ($fresh->restaurant_table_id) {
                    \App\Models\RestaurantTable::query()
                        ->where('id', $fresh->restaurant_table_id)
                        ->update(['status' => 'available']);
                }

                // NOTE: do NOT set completed_at here. That's reserved for the
                // reception discharge step. Setting it now would lock the
                // visit out of the awaiting_payment phase (visitAcceptsPayments
                // refuses visits with completed_at set, matching Filament's
                // visitIsOpen logic).
                $fresh->status = Visit::STATUS_AWAITING_PAYMENT;
                $fresh->updated_by_user_id = auth()->id();
                $fresh->save();
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        // Alert reception users at the same branch so they can take payment.
        // Uses the same direct-insert pattern as VisitPaymentObserver — the
        // existing v2 NotificationPoller picks these up and toasts them.
        $this->notifyReceptionForBilling($visit->fresh());

        return response()->json(['ok' => true]);
    }

    /**
     * Build the insurance block surfaced in transformVisit(). Includes
     * any active policies for the patient, the latest non-void claim
     * (if any), and the skip stamp (if any). Used by VisitSheet to
     * render the banner + Create-claim / Skip buttons.
     */
    protected function insurancePayloadFor(Visit $v): array
    {
        if (! class_exists(\App\Models\Insurance\PatientInsurancePolicy::class)) {
            return [
                'active_policies' => [],
                'claim' => null,
                'skipped_at' => null,
                'requires_decision' => false,
            ];
        }

        $policies = $v->patient_id
            ? \App\Models\Insurance\PatientInsurancePolicy::query()
                ->active()
                ->where('patient_id', $v->patient_id)
                ->with(['insurer:id,name', 'plan:id,name,code'])
                ->orderByDesc('is_primary')
                ->orderBy('priority')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'policy_number' => $p->policy_number,
                    'is_primary' => (bool) $p->is_primary,
                    'insurer_name' => $p->insurer?->name,
                    'plan_name' => is_array($p->plan?->name)
                        ? ($p->plan->name[app()->getLocale()] ?? $p->plan->name['en'] ?? reset($p->plan->name))
                        : ($p->plan?->name ?? null),
                ])
                ->values()
                ->all()
            : [];

        $claim = \App\Models\Insurance\InsuranceClaim::query()
            ->where('visit_id', $v->id)
            ->where('status', '!=', \App\Models\Insurance\InsuranceClaim::STATUS_VOID)
            ->orderByDesc('id')
            ->first();

        return [
            'active_policies' => $policies,
            'claim' => $claim ? [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'status' => $claim->status,
                'total_charged' => (float) $claim->total_charged,
                'patient_copay' => (float) $claim->patient_copay,
                'insurer_payable' => (float) $claim->insurer_payable,
            ] : null,
            'skipped_at' => optional($v->insurance_claim_skipped_at)->toIso8601String(),
            'skip_reason' => $v->insurance_claim_skip_reason,
            'requires_decision' => $this->requiresInsuranceDecision($v),
        ];
    }

    /**
     * Fire a database notification to every reception/admin user at the
     * visit's branch when the doctor finishes treatment. The v2
     * NotificationPoller renders these as toasts + bell-badge increments.
     */
    protected function notifyReceptionForBilling(\App\Models\Visit $visit): void
    {
        try {
            $patientName = $visit->patient?->name ?? ('#'.$visit->id);
            $doctorName = $visit->doctor?->name ?? '';
            $bookingCode = $visit->booking_code ?? '';

            // Recipients: clinic_reception, admin, super_admin, clinic_admin
            // at the same branch as the visit. BelongsToBranchScope on User
            // doesn't apply, so we filter manually with a subquery.
            $recipientIds = \App\Models\User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                    'clinic_reception', 'admin', 'super_admin', 'clinic_admin',
                ]))
                ->when($visit->branch_id, function ($q) use ($visit) {
                    // If the User model has a branches relation, prefer that.
                    // Otherwise notify all matching roles (safer to over-notify
                    // than miss the actual desk that should act).
                    if (\Schema::hasTable('branch_user')) {
                        $q->whereExists(function ($sub) use ($visit) {
                            $sub->select(\DB::raw(1))
                                ->from('branch_user')
                                ->whereColumn('branch_user.user_id', 'users.id')
                                ->where('branch_user.branch_id', $visit->branch_id);
                        });
                    }
                })
                ->pluck('id')
                ->all();

            if (empty($recipientIds)) {
                return;
            }

            $payload = [
                'title' => 'Ready for payment',
                'body' => trim($patientName.($bookingCode ? ' · '.$bookingCode : '').($doctorName ? ' · '.$doctorName : '')),
                'icon' => 'credit-card',
                'iconColor' => 'warning',
                'kind' => 'billing',
                'actions' => [
                    [
                        'name' => 'open_visit',
                        'url' => '/admin/v2/waiting-patients',
                    ],
                ],
            ];

            $now = now();
            $rows = array_map(fn ($uid) => [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\VisitReadyForBilling',
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id' => $uid,
                'data' => json_encode($payload),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $recipientIds);

            \DB::table('notifications')->insert($rows);
        } catch (\Throwable $e) {
            // Notification failure should never block the finish-treatment flow.
            report($e);
        }
    }

    /**
     * Insurance-gate helper. Returns true when the patient has an active
     * insurance policy AND reception hasn't decided yet (no draft+ claim
     * for this visit AND no explicit skip stamp). Discharge blocks while
     * this returns true.
     */
    protected function requiresInsuranceDecision(Visit $visit): bool
    {
        if (! class_exists(\App\Models\Insurance\PatientInsurancePolicy::class)) {
            return false; // module not installed
        }
        if (! $visit->patient_id) {
            return false;
        }

        $hasActive = \App\Models\Insurance\PatientInsurancePolicy::query()
            ->active()
            ->where('patient_id', $visit->patient_id)
            ->exists();

        if (! $hasActive) {
            return false;
        }

        // Already explicitly skipped — let it through.
        if (! empty($visit->insurance_claim_skipped_at)) {
            return false;
        }

        // Already filed a (non-void) claim — let it through.
        $hasClaim = \App\Models\Insurance\InsuranceClaim::query()
            ->where('visit_id', $visit->id)
            ->where('status', '!=', \App\Models\Insurance\InsuranceClaim::STATUS_VOID)
            ->exists();

        return ! $hasClaim;
    }

    /**
     * Reception explicitly skips filing an insurance claim for this visit
     * — "patient is paying cash, no claim needed". Stamps the visit so
     * the discharge gate stops blocking.
     */
    public function skipInsuranceClaim(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'Only reception or admin can skip an insurance claim.'], 403);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $visit->update([
            'insurance_claim_skipped_at' => now(),
            'insurance_claim_skipped_by_user_id' => auth()->id(),
            'insurance_claim_skip_reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Create a draft InsuranceClaim for this visit against the patient's
     * primary active policy. Reuses InsuranceService::createClaimFromVisit
     * which is idempotent — calling it twice returns the same claim.
     */
    public function createInsuranceClaim(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'Only reception or admin can file an insurance claim.'], 403);
        }
        if (! class_exists(\App\Models\Insurance\PatientInsurancePolicy::class)) {
            return response()->json(['ok' => false, 'error' => 'Insurance module is not available.'], 422);
        }

        $policyId = (int) $request->input('policy_id', 0);

        $policy = $policyId
            ? \App\Models\Insurance\PatientInsurancePolicy::query()->active()
                ->where('patient_id', $visit->patient_id)
                ->where('id', $policyId)
                ->first()
            : \App\Models\Insurance\PatientInsurancePolicy::query()->active()
                ->where('patient_id', $visit->patient_id)
                ->orderByDesc('is_primary')
                ->orderBy('priority')
                ->orderByDesc('id')
                ->first();

        if (! $policy) {
            return response()->json(['ok' => false, 'error' => 'No active insurance policy found for this patient.'], 422);
        }

        $actor = $request->user() ?? auth()->user();
        if (! $actor) {
            return response()->json(['ok' => false, 'error' => 'Not authenticated.'], 401);
        }

        try {
            /** @var \App\Models\Insurance\InsuranceClaim $claim */
            $claim = app(\App\Services\Insurance\InsuranceService::class)
                ->createClaimFromVisit($visit, $policy, $actor);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        // Clear any previous skip stamp — claim now exists.
        if ($visit->insurance_claim_skipped_at) {
            $visit->update([
                'insurance_claim_skipped_at' => null,
                'insurance_claim_skipped_by_user_id' => null,
                'insurance_claim_skip_reason' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'claim' => [
                'id' => $claim->id,
                'claim_number' => $claim->claim_number,
                'status' => $claim->status,
                'total_charged' => (float) $claim->total_charged,
                'patient_copay' => (float) $claim->patient_copay,
                'insurer_payable' => (float) $claim->insurer_payable,
            ],
        ]);
    }

    /**
     * Mark the pending stock request as fulfilled and resume the visit.
     * Mirrors WaitingPatients::fulfillStockAction in Filament: resumes to
     * in_progress if the doctor already accepted, otherwise awaiting_doctor.
     */
    /**
     * Doctor manually requests items from stock. Mirrors Filament
     * WaitingPatients::requestStockAction — the visit moves to
     * awaiting_stock while reception/nursing fulfils the request.
     *
     * Use cases:
     *   - Item missing from the visit's billed services but needed for treatment
     *   - Pre-emptively reserve stock before adding to the visit billing
     */
    public function requestStock(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to request stock on this visit.'], 403);
        }

        if (! $this->visitAcceptsClinicalEdits($visit)) {
            return response()->json([
                'ok' => false,
                'error' => $this->clinicalEditsRejectionReason($visit),
            ], 422);
        }

        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.clinic_item_id' => 'required|integer|exists:clinic_items,id',
            'items.*.qty_base' => 'required|numeric|min:0.0001',
            'notes' => 'nullable|string|max:2000',
        ]);

        $lines = collect($data['items'])
            ->map(fn ($r) => ['clinic_item_id' => (int) $r['clinic_item_id'], 'qty_base' => (float) $r['qty_base']])
            ->values()
            ->all();

        try {
            $req = app(\App\Services\Clinic\VisitStockRequestService::class)
                ->createForVisit(
                    $visit,
                    $lines,
                    (int) (auth()->id() ?? 0),
                    $data['notes'] ?? null,
                    true // setVisitAwaitingStock
                );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'request_id' => $req?->id,
            'items' => count($lines),
        ]);
    }

    public function fulfillStock(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canOperateVisit($visit)) {
            return response()->json(['ok' => false, 'error' => 'You are not authorised to fulfil stock on this visit.'], 403);
        }

        $visit->load('pendingStockRequest');
        $req = $visit->pendingStockRequest;
        if (! $req) {
            return response()->json([
                'ok' => false,
                'error' => 'No pending stock request — items may already be issued.',
            ], 422);
        }

        $resume = ($visit->accepted_at || $visit->accepted_by_user_id || $visit->service_started_at)
            ? \App\Models\Visit::STATUS_IN_PROGRESS
            : \App\Models\Visit::STATUS_AWAITING_DOCTOR;

        try {
            app(\App\Services\Clinic\VisitStockRequestService::class)
                ->fulfill($req, (int) (auth()->id() ?? 0), $request->input('notes'), $resume);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'resumed' => $resume]);
    }

    /**
     * Reception discharge — closes the visit + booking. Mirrors Filament
     * BookingResource::discharge:
     *   - guards: checked-in, has visit, consultation paid, visit open,
     *     balance == 0 (live recompute, not snapshot)
     *   - action: free room, booking.status=completed, visit.status=completed,
     *     visit.completed_at = now (this is the ONLY place completed_at is set)
     */
    public function discharge(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'Only reception or admin can discharge a patient.'], 403);
        }

        if (! $this->visitIsCheckedIn($visit)) {
            return response()->json(['ok' => false, 'error' => 'Cannot discharge: patient was never checked in.'], 422);
        }

        if ($this->visitIsTerminal($visit) || ! empty($visit->completed_at)) {
            return response()->json(['ok' => false, 'error' => 'Visit is already closed.'], 422);
        }

        // Insurance gate (Option 2): if the patient has an active policy,
        // reception must either file a claim OR explicitly skip before
        // the visit can be closed. Stops the "we forgot to bill" mistake.
        if ($this->requiresInsuranceDecision($visit)) {
            return response()->json([
                'ok' => false,
                'error' => 'This patient has an active insurance policy. Create a claim or explicitly skip before discharge.',
                'reason' => 'insurance_decision_pending',
            ], 422);
        }

        // Live balance recompute (don't trust snapshot columns), through the
        // same service the payment endpoints cap against — lines net of each
        // per-line discount, then the visit-level discount, then payments.
        // Using gross line totals here would reject a discharge the UI shows as
        // allowed whenever a package/item carries a line discount or promo.
        $balanceSvc = app(\App\Services\Clinic\VisitBalanceService::class);
        $balance = round($balanceSvc->billed($visit) - $balanceSvc->paid($visit), 3);

        if ($balance > \App\Services\Clinic\VisitBalanceService::TOLERANCE) {
            return response()->json([
                'ok' => false,
                'error' => 'Cannot discharge — outstanding balance: '.number_format($balance, 3).' KWD. Collect payment first.',
            ], 422);
        }

        // Deliberately no "a payment tagged kind=consultation must exist" gate
        // here. `kind` is a revenue-posting label the cashier picks, not proof
        // of collection: settling the whole bill under 'other' (or 'services')
        // used to leave this check at zero and refuse a discharge on a visit
        // that owed nothing. The zero-balance check above already guarantees
        // every charge on the visit — consultation included — has been paid.

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($visit) {
                /** @var Visit $fresh */
                $fresh = Visit::query()->lockForUpdate()->findOrFail($visit->id);

                // Free the room (defensive — Filament does the same, even
                // though doctor's complete already releases it).
                if ($fresh->restaurant_table_id) {
                    \App\Models\RestaurantTable::query()
                        ->where('id', $fresh->restaurant_table_id)
                        ->update(['status' => 'available']);
                }

                $now = now();

                $fresh->update([
                    'status' => Visit::STATUS_COMPLETED,
                    'completed_at' => $now,
                    'service_started_at' => $fresh->service_started_at ?? $now,
                    'updated_by_user_id' => auth()->id(),
                ]);

                // Close the booking too. Preserve checked_in_at so attendance
                // reports still count this visit.
                if ($fresh->booking_id) {
                    \App\Models\Booking::query()
                        ->where('id', $fresh->booking_id)
                        ->update(['status' => \App\Models\Booking::S_COMPLETED]);
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Reception/admin reassigns the visit's doctor from the queue. Branch-safe:
     * the chosen doctor must be at the visit's branch (the Doctor scope already
     * limits a non-admin's options to their own clinic). Also re-points the
     * booking so schedules stay consistent. Not allowed on a closed visit.
     */
    public function reassignDoctor(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canCollectPayment($visit)) {
            return response()->json(['ok' => false, 'error' => 'Only reception or admin can change the doctor.'], 403);
        }
        if ($this->visitIsTerminal($visit) || ! empty($visit->completed_at)) {
            return response()->json(['ok' => false, 'error' => 'This visit is closed.'], 422);
        }

        $data = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'force' => 'sometimes|boolean',
        ]);

        // Once the patient is past the waiting room (in_progress / awaiting_stock
        // / awaiting_payment), the assigned doctor is effectively "who treated
        // them" — rewriting it skews reports and the doctor's schedule. Allow it
        // only for an admin who explicitly confirms the override.
        if ($visit->status !== Visit::STATUS_AWAITING_DOCTOR) {
            if (! $this->isAdminUser() || ! (bool) ($data['force'] ?? false)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'This visit has already started — only an admin can reassign the doctor, and must confirm the override.',
                    'requires_force' => $this->isAdminUser(),
                ], 422);
            }
        }

        $doctor = \App\Models\Doctor::query()->find((int) $data['doctor_id']);
        if (! $doctor) {
            return response()->json(['ok' => false, 'error' => 'Doctor not found or not in your clinic.'], 422);
        }
        if ($visit->branch_id && $doctor->branch_id && (int) $doctor->branch_id !== (int) $visit->branch_id) {
            return response()->json(['ok' => false, 'error' => "Selected doctor is not at this visit's branch."], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($visit, $doctor) {
            $visit->forceFill(['doctor_id' => $doctor->id, 'updated_by_user_id' => auth()->id()])->save();
            if ($visit->booking_id) {
                \App\Models\Booking::query()->where('id', $visit->booking_id)->update(['doctor_id' => $doctor->id]);
            }
        });

        return response()->json(['ok' => true, 'doctor' => ['id' => $doctor->id, 'name' => $doctor->name]]);
    }

    /**
     * Create a hub → this-branch stock transfer for the visit's short
     * consumables (the items it's awaiting that the branch can't cover). Once a
     * dispatcher dispatches the transfer, the branch has stock and the visit's
     * pending stock request can be fulfilled.
     */
    public function sourceFromHub(Request $request, Visit $visit): \Illuminate\Http\JsonResponse
    {
        if (! $this->canManageVisitPackages($visit)) {
            return response()->json(['ok' => false, 'error' => 'Not authorised.'], 403);
        }

        $partnerId = (int) ($visit->branch?->partner_id ?? 0);
        $svc = app(\App\Services\Clinic\StockTransferService::class);
        $hub = $svc->hubBranchId($partnerId ?: null);
        $branchId = (int) ($visit->branch_id ?? 0);

        if (! $hub) {
            return response()->json(['ok' => false, 'error' => 'No hub is set for this clinic.'], 422);
        }
        if ($hub === $branchId) {
            return response()->json(['ok' => false, 'error' => 'This visit is at the hub branch.'], 422);
        }

        $pendingReq = \App\Models\VisitStockRequest::query()
            ->where('visit_id', $visit->id)
            ->where('status', \App\Models\VisitStockRequest::STATUS_PENDING)
            ->with('lines')->first();
        if (! $pendingReq) {
            return response()->json(['ok' => false, 'error' => 'Nothing is awaiting stock on this visit.'], 422);
        }

        $lines = [];
        foreach ($pendingReq->lines as $ln) {
            $onHand = (float) \App\Models\ClinicItemStock::query()
                ->where('branch_id', $branchId)->where('clinic_item_id', $ln->clinic_item_id)->value('qty_on_hand_base');
            $short = max(0, (float) $ln->qty_base - $onHand);
            if ($short > 0) {
                $lines[] = ['clinic_item_id' => (int) $ln->clinic_item_id, 'qty_base' => $short];
            }
        }
        if (! $lines) {
            return response()->json(['ok' => false, 'error' => 'Nothing is short at this branch.'], 422);
        }

        try {
            $transfer = $svc->create($partnerId, $hub, $branchId, $lines, (int) (auth()->id() ?? 0), $visit->id, 'Sourced for visit #'.$visit->id);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'transfer_id' => $transfer->id, 'lines' => count($lines)]);
    }

    protected function transformVisit(Visit $v): array
    {
        $age = null;
        if ($v->patient && $v->patient->dob) {
            try {
                $age = Carbon::parse($v->patient->dob)->age;
            } catch (\Throwable) {
            }
        }

        $feeAmount = (float) ($v->doctor->consultation_fee ?? 0);
        $paidConsultation = (float) VisitPayment::query()
            ->where('visit_id', $v->id)
            ->where('kind', VisitPayment::KIND_CONSULTATION)
            ->where('status', 'paid')
            ->sum('amount');

        $paidTotal = (float) VisitPayment::query()
            ->where('visit_id', $v->id)
            ->where('status', 'paid')
            ->sum('amount');

        // ── Per-item stock lookup at the visit's branch ────────────────────
        // For each item we surface qty_on_hand, qty_short and a state flag
        // so the doctor can see what's covered vs what needs to be requested.
        $branchId = (int) ($v->branch_id ?? 0);
        $itemIds = $v->visitItems->pluck('clinic_item_id')->filter()->unique()->all();
        $stockByItem = [];
        $stockableByItem = [];
        if ($branchId > 0 && count($itemIds) > 0) {
            $stockByItem = \App\Models\ClinicItemStock::query()
                ->where('branch_id', $branchId)
                ->whereIn('clinic_item_id', $itemIds)
                ->pluck('qty_on_hand_base', 'clinic_item_id')
                ->all();
            $stockableByItem = \App\Models\ClinicItem::query()
                ->whereIn('id', $itemIds)
                ->pluck('is_stockable', 'id')
                ->all();
        }

        $stockShortages = [];

        $items = $v->visitItems->map(function ($it) use ($stockByItem, $stockableByItem, &$stockShortages) {
            $itemId = (int) ($it->clinic_item_id ?? 0);
            $needed = (float) ($it->qty ?? 0);
            $isStockable = (bool) ($stockableByItem[$itemId] ?? false);
            $onHand = (float) ($stockByItem[$itemId] ?? 0);
            $shortage = $isStockable ? max(0, $needed - $onHand) : 0;

            // Only stockable items show stock state. Services / billable-only
            // rows have no stock concept, so the UI shows nothing for them.
            $state = null;
            if ($isStockable) {
                if ($shortage > 0) {
                    $state = 'out';
                }      // need to request
                elseif ($onHand <= $needed) {
                    $state = 'low';
                }    // exactly covered or tight
                else {
                    $state = 'in_stock';
                }
            }

            if ($isStockable && $shortage > 0) {
                $stockShortages[] = [
                    'clinic_item_id' => $itemId,
                    'name' => $it->clinicItem ? $this->resolveName($it->clinicItem->name) : ('#'.$itemId),
                    'qty_needed' => $needed,
                    'qty_on_hand' => $onHand,
                    'qty_short' => round($shortage, 4),
                ];
            }

            return [
                'id' => $it->id,
                'clinic_item_id' => $itemId,
                'name' => $it->clinicItem ? $this->resolveName($it->clinicItem->name) : ('#'.$itemId),
                'qty' => $needed,
                'unit_price' => (float) ($it->unit_price_snapshot ?? 0),
                'line_total' => (float) ($it->line_price_total ?? 0),
                'discount_amount' => (float) ($it->discount_amount ?? 0),
                'discount_source' => $it->discount_source,
                'net_total' => max(0, (float) ($it->line_price_total ?? 0) - (float) ($it->discount_amount ?? 0)),
                'is_stockable' => $isStockable,
                'qty_on_hand' => $isStockable ? $onHand : null,
                'qty_short' => $isStockable ? round($shortage, 4) : 0,
                'stock_state' => $state,
            ];
        })->values();

        // ── Pending consumables the visit is awaiting (from packages/services) ──
        // When a package/service is added and stock is short, its bill-of-materials
        // consumables go onto a pending stock request (not onto the visit as items).
        // Surface those lines + their per-branch stock state so the UI can show
        // "what's awaiting" and the Request-stock modal can pre-fill them.
        $pendingStock = [];
        $pendingReq = $v->relationLoaded('pendingStockRequest')
            ? $v->pendingStockRequest
            : \App\Models\VisitStockRequest::query()
                ->where('visit_id', $v->id)
                ->where('status', \App\Models\VisitStockRequest::STATUS_PENDING)
                ->with('lines.clinicItem')
                ->first();
        if ($pendingReq) {
            $pendingReq->loadMissing('lines.clinicItem');
            $reqItemIds = $pendingReq->lines->pluck('clinic_item_id')->filter()->unique()->all();
            $reqStock = ($branchId > 0 && $reqItemIds)
                ? \App\Models\ClinicItemStock::query()->where('branch_id', $branchId)
                    ->whereIn('clinic_item_id', $reqItemIds)->pluck('qty_on_hand_base', 'clinic_item_id')->all()
                : [];
            foreach ($pendingReq->lines as $ln) {
                $cid = (int) $ln->clinic_item_id;
                $need = (float) ($ln->qty_base ?? 0);
                $onHand = (float) ($reqStock[$cid] ?? 0);
                $pendingStock[] = [
                    'clinic_item_id' => $cid,
                    'name' => $ln->clinicItem ? $this->resolveName($ln->clinicItem->name) : ('#'.$cid),
                    'qty' => $need,
                    'qty_on_hand' => $onHand,
                    'qty_short' => round(max(0, $need - $onHand), 4),
                ];
            }
        }

        $packages = $v->visitPackages->map(fn ($vp) => [
            'id' => $vp->id,
            'package_id' => $vp->clinic_package_id,
            'name' => $vp->package ? $this->resolveName($vp->package->name) : ('#'.$vp->clinic_package_id),
            'qty' => (float) ($vp->qty ?? 0),
            'unit_price' => (float) ($vp->unit_price_snapshot ?? 0),
            'line_total' => (float) ($vp->line_total ?? 0),
            'discount_amount' => (float) ($vp->discount_amount ?? 0),
            'discount_source' => $vp->discount_source,
            'net_total' => max(0, (float) ($vp->line_total ?? 0) - (float) ($vp->discount_amount ?? 0)),
            // What this bundle includes (read-only) so the line isn't opaque.
            'contents' => $vp->package?->items->map(fn ($pi) => [
                'name' => $pi->clinicItem ? $this->resolveName($pi->clinicItem->name) : ('#'.$pi->clinic_item_id),
                'qty' => (float) ($pi->qty_base ?? 0),
            ])->values() ?? [],
        ])->values();

        $payments = $v->payments->map(fn ($p) => [
            'id' => $p->id,
            'amount' => (float) $p->amount,
            'method' => $p->method,
            'status' => $p->status,
            'kind' => $p->kind,
            'reference_no' => $p->reference_no,
            'paid_at' => optional($p->paid_at)->toIso8601String(),
            'collected_by_user_id' => $p->collected_by_user_id,
        ])->values();

        $canOperate = $this->canOperateVisit($v);
        $canCollect = $this->canCollectPayment($v);
        $isTerminal = $this->visitIsTerminal($v);
        $isCheckedIn = $this->visitIsCheckedIn($v);
        $acceptsClinical = $this->visitAcceptsClinicalEdits($v);
        $acceptsPayments = $this->visitAcceptsPayments($v);

        // Live balance — lines net of each per-line discount, then the
        // visit-level discount, then payments. Matches VisitCostingService.
        $feesSum = (float) \App\Models\VisitCharge::query()->where('visit_id', $v->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN line_total - discount_amount > 0 THEN line_total - discount_amount ELSE 0 END), 0) as t')->value('t');
        $packagesSum = (float) \App\Models\VisitPackage::query()->where('visit_id', $v->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN line_total - discount_amount > 0 THEN line_total - discount_amount ELSE 0 END), 0) as t')->value('t');
        $itemsSum = (float) \App\Models\VisitItem::query()->where('visit_id', $v->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN line_price_total - discount_amount > 0 THEN line_price_total - discount_amount ELSE 0 END), 0) as t')->value('t');
        $subtotal = round($feesSum + $packagesSum + $itemsSum, 3);
        $discount = (float) ($v->discount_total ?? 0);
        $balance = max(0, round(($subtotal - $discount) - $paidTotal, 3));

        $hasPendingStock = $v->relationLoaded('pendingStockRequest')
            ? (bool) $v->pendingStockRequest
            : \App\Models\VisitStockRequest::query()
                ->where('visit_id', $v->id)
                ->where('status', \App\Models\VisitStockRequest::STATUS_PENDING)
                ->exists();

        // Doctor busy guard: a doctor handles one patient at a time
        // (admin bypasses). True if the doctor for THIS visit already
        // has *another* visit currently in_progress.
        $doctorIsBusy = false;
        if (! $this->isAdminUser() && $v->doctor_id && $this->doctorIdForCurrentUser() === (int) $v->doctor_id) {
            $doctorIsBusy = Visit::query()
                ->where('doctor_id', $v->doctor_id)
                ->where('status', Visit::STATUS_IN_PROGRESS)
                ->where('id', '!=', $v->id)
                ->exists();
        }

        return [
            'id' => $v->id,
            'status' => $v->status,
            'booking_code' => $v->booking_code,
            'booking_id' => $v->booking_id,
            'checked_in_at' => optional($v->checked_in_at)->toIso8601String(),
            'queued_at' => optional($v->queued_at)->toIso8601String(),
            'service_started_at' => optional($v->service_started_at)->toIso8601String(),
            'completed_at' => optional($v->completed_at)->toIso8601String(),

            'chief_complaint' => $v->chief_complaint,
            'history' => $v->history,
            'examination' => $v->examination,
            'diagnosis' => $v->diagnosis,
            'prescriptions' => $v->prescriptions,
            'patient_instructions' => $v->patient_instructions,
            'lab_requests' => $v->lab_requests,
            'sick_leave_days' => $v->sick_leave_days,
            'follow_up_date' => optional($v->follow_up_date)->toDateString(),
            'notes' => $v->notes,
            'vitals' => $v->vitals,

            'fee' => [
                'amount' => $feeAmount,
                'paid_consultation' => $paidConsultation,
                'paid_total' => $paidTotal,
                'consultation_paid' => $feeAmount > 0 && $paidConsultation >= $feeAmount,
            ],
            'totals' => [
                'fees' => (float) ($v->fees_total ?? 0),
                'items_price' => (float) ($v->items_price_total ?? 0),
                'packages_price' => (float) ($v->packages_price_total ?? 0),
                'discount' => (float) ($v->discount_total ?? 0),
                'subtotal' => $subtotal,
                'profit' => (float) ($v->profit_total ?? 0),
            ],

            // Visit-level discount + applied coupon — drives the checkout UI.
            'discount' => [
                'type' => $v->discount_type,          // 'amount' | 'percent' | null
                'value' => (float) ($v->discount_value ?? 0),
                'total' => (float) ($v->discount_total ?? 0),
                'coupon_code' => $v->coupon_code,
                'subtotal' => $subtotal,
            ],

            'patient' => $v->patient ? [
                'id' => $v->patient->id,
                'name' => $v->patient->name,
                'msisdn' => $v->patient->phone ?? null,
                'age' => $age,
                'gender' => $v->patient->gender ?? null,
                'civil_id' => $v->patient->civil_id ?? null,
                'allergies' => $v->patient->allergies ?? null,
                'blood_group' => $v->patient->blood_group ?? null,
            ] : null,
            'doctor' => $v->doctor ? [
                'id' => $v->doctor->id,
                'name' => $v->doctor->name,
                'specialty' => $v->doctor->specialty ?? null,
            ] : null,
            'branch' => $v->branch ? [
                'id' => $v->branch->id,
                'name' => $v->branch->getTranslation('name', app()->getLocale(), true),
            ] : null,
            'room' => $v->room ? [
                'id' => $v->room->id,
                'name' => $v->room->name,
            ] : null,

            'items' => $items,
            'packages' => $packages,
            'payments' => $payments,

            // Offer the patient selected on the public website when booking.
            // Null when there was none, or once it's already on the visit.
            'requested_package' => $this->requestedPackagePayload($v),

            // Filament fallbacks for actions we haven't ported yet.
            'edit_url' => '/admin/visits/'.$v->id.'/edit',

            'pending_stock_request' => $hasPendingStock,
            'balance' => $balance,
            // Items on this visit that need restocking (stockable items
            // where qty_on_hand < qty needed). Used by VisitSheet to show
            // a one-click "Request missing stock" smart button.
            'stock_shortages' => array_values($stockShortages),
            // Consumables the visit is awaiting from its packages/services.
            'pending_stock' => $pendingStock,

            // Insurance decision state — drives the discharge gate.
            'insurance' => $this->insurancePayloadFor($v),

            // Permissions for the current user — VisitSheet uses these to
            // decide which buttons to render. The backend still enforces.
            'permissions' => [
                'can_operate' => $canOperate,
                'can_edit_clinical' => $canOperate && $acceptsClinical,
                'can_manage_items' => $canOperate && $acceptsClinical,
                'can_manage_packages' => ($canOperate || $canCollect) && $acceptsClinical,
                'can_record_payment' => $canCollect && $acceptsPayments,
                'can_start' => $canOperate
                    && in_array($v->status, [Visit::STATUS_AWAITING_DOCTOR, Visit::STATUS_AWAITING_STOCK], true)
                    && ! empty($v->checked_in_at)
                    && ! $doctorIsBusy,
                'doctor_busy_elsewhere' => $doctorIsBusy,
                'can_complete' => $canOperate
                    && $v->status === Visit::STATUS_IN_PROGRESS
                    && ! $hasPendingStock,
                'can_request_stock' => $canOperate && $acceptsClinical,
                'can_fulfill_stock' => $canOperate
                    && $v->status === Visit::STATUS_AWAITING_STOCK
                    && $hasPendingStock,
                'can_discharge' => $canCollect
                    && $isCheckedIn
                    && empty($v->completed_at)
                    && ! $isTerminal
                    && $v->status === Visit::STATUS_AWAITING_PAYMENT
                    && $balance <= 0.005
                    && ! $this->requiresInsuranceDecision($v),
                'is_admin' => $this->isAdminUser(),
                'is_reception' => $this->isReceptionUser() && ! $this->isAdminUser(),
                'is_doctor' => $this->isDoctorUser(),
                'is_checked_in' => $isCheckedIn,
            ],
            // Admin-configured payment methods for this branch's clinic, plus
            // whether an online (MyFatoorah) link can be generated. Drives the
            // payment modal's method picker + the "payment link / QR" action.
            'payment_methods' => app(\App\Services\Clinic\ClinicPaymentMethodResolver::class)
                ->forBranch((int) $v->branch_id, (int) ($v->branch?->partner_id ?? 0)),
            'online_payment_available' => (bool) \App\Models\GatewayAccount::bestForBranch(
                (int) $v->branch_id,
                (int) ($v->branch?->partner_id ?? 0) ?: null,
            ),
            // Last 5 visits for the same patient (excludes this one) — used
            // by VisitSheet's History tab to give the doctor quick context.
            'recent_visits' => $this->recentVisitsFor($v),
        ];
    }

    /**
     * The offer the patient picked on the public website when they booked, so
     * the doctor sees it the moment they open the console instead of hunting
     * for it. The request lives on the BOOKING, which would otherwise be lost
     * once reception converts it into a visit.
     *
     * Returns null once the package is actually on the visit — an honoured
     * request is noise from that point on. Same rule (and same payload shape)
     * as WaitingPatientsController::formatRequestedPackage() /
     * requestedPackageByVisit(); the two are duplicated on purpose so neither
     * controller depends on the other. Keep them in sync if either changes.
     *
     * No extra queries: `booking.requestedPackage` and `visitPackages` are both
     * eager-loaded by show()/showJson().
     */
    protected function requestedPackagePayload(Visit $v): ?array
    {
        $booking = $v->relationLoaded('booking') ? $v->booking : null;
        $p = $booking?->relationLoaded('requestedPackage') ? $booking->requestedPackage : null;
        if (! $p) {
            return null;
        }

        // Already applied to this visit → the banner has served its purpose.
        $alreadyOnVisit = $v->visitPackages
            ->contains(fn ($vp) => (int) $vp->clinic_package_id === (int) $p->id);
        if ($alreadyOnVisit) {
            return null;
        }

        return [
            'id' => $p->id,
            'name' => $p->localized_name,
            'price' => round((float) $p->effective_price, 3),
            'has_discount' => (bool) $p->has_discount,
            // Offers are branch-scoped: a patient can pick an offer published by
            // one branch and then be seen at another. Warn rather than silently
            // sell the wrong thing — but only when the package is pinned to a
            // branch (a partner-wide package with a null branch_id is valid
            // everywhere).
            'branch_mismatch' => $p->branch_id !== null
                && $v->branch_id !== null
                && (int) $p->branch_id !== (int) $v->branch_id,
        ];
    }

    protected function recentVisitsFor(Visit $visit): array
    {
        if (! $visit->patient_id) {
            return [];
        }

        return Visit::query()
            ->where('patient_id', $visit->patient_id)
            ->whereKeyNot($visit->id)
            ->orderByDesc('checked_in_at')
            ->limit(5)
            ->get(['id', 'status', 'checked_in_at', 'doctor_id', 'diagnosis'])
            ->map(function ($v) {
                return [
                    'id' => $v->id,
                    'status' => $v->status,
                    'date' => optional($v->checked_in_at)->toIso8601String(),
                    'doctor_name' => $v->doctor?->name,
                    'diagnosis' => $v->diagnosis,
                ];
            })
            ->all();
    }
}
