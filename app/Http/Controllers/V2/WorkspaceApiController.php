<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Workspace\OrderSet;
use App\Models\Workspace\QueueCall;
use App\Models\Workspace\VisitEvent;
use App\Models\Workspace\VisitDocument;
use App\Models\VisitPayment;
use Illuminate\Support\Facades\DB;
use App\Support\VisitAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The visit workspace's own endpoints — only the things v2 had nowhere to
 * put: vitals, allergy/alert editing, a visit's clinical context (last
 * vitals, last prescription), saved order sets, and waiting-room calls.
 *
 * Everything else the workspace does goes through the existing v2 APIs.
 * Visit lookups go through route-model binding, so the Visit branch scope
 * applies exactly as it does on every other v2 visit endpoint.
 */
class WorkspaceApiController extends Controller
{
    use VisitAuthorization;

    /* ── who may do what ─────────────────────────────────────────────── */

    protected function isNurseUser(): bool
    {
        $u = $this->authUser();

        return (bool) ($u && method_exists($u, 'hasRole') && $u->hasRole('clinic_nurse'));
    }

    /** Clinical hands on this visit: its doctor, an admin, or a nurse. */
    protected function canTouchClinical(Visit $visit): bool
    {
        return $this->canOperateVisit($visit) || $this->isNurseUser();
    }

    protected function deny(string $msg = 'Not authorized.', int $code = 403): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $msg], $code);
    }

    /* ── context: what the workspace shows beside a visit ───────────── */

    public function context(Visit $visit): JsonResponse
    {
        // The people who work this visit: its doctor or an admin, reception, a nurse.
        if (! ($this->canTouchClinical($visit) || $this->isReceptionUser())) {
            return $this->deny();
        }
        $previous = Visit::query()
            ->where('patient_id', $visit->patient_id)
            ->where('id', '<', $visit->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'vitals', 'prescriptions', 'checked_in_at', 'created_at']);

        $lastVitals = $previous->first(fn ($v) => ! empty($v->vitals));
        $lastRx = $previous->first(fn ($v) => ! empty($v->prescriptions));

        $patient = $visit->patient;

        return response()->json([
            'ok' => true,
            'vitals' => $visit->vitals ?: null,
            'last_vitals' => $lastVitals ? array_merge((array) $lastVitals->vitals, [
                'date' => optional($lastVitals->checked_in_at ?? $lastVitals->created_at)->toDateString(),
            ]) : null,
            'last_rx' => $lastRx ? $this->rxLines($lastRx->prescriptions) : [],
            'allergies' => $patient?->allergies,
            'medical_alerts' => $patient?->medical_alerts,
            // Same rule the vitals save enforces: an allowed user AND a visit still open to clinical edits.
            'can_edit_clinical' => $this->canTouchClinical($visit) && $this->visitAcceptsClinicalEdits($visit),
            // Allergies and alerts belong to the patient, not this visit: editable whatever the visit's status.
            'can_edit_alerts' => $this->canTouchClinical($visit),
            'events' => Schema::hasTable('visit_events')
                ? VisitEvent::query()->where('visit_id', $visit->id)->orderBy('at')->limit(200)
                    ->get(['id', 'kind', 'text', 'user_id', 'at'])
                    ->map(fn ($e) => ['id' => 'e'.$e->id, 'kind' => $e->kind, 'text' => $e->text, 'at' => $e->at?->toIso8601String()])
                    ->values()
                : [],
        ]);
    }

    /** Record one timeline line for a visit (anyone who works the visit). */
    public function logEvent(Request $request, Visit $visit): JsonResponse
    {
        if (! Schema::hasTable('visit_events')) {
            return response()->json(['ok' => true, 'stored' => false]);
        }
        if (! ($this->canTouchClinical($visit) || $this->isReceptionUser())) {
            return $this->deny();
        }
        $data = $request->validate([
            'kind' => ['required', 'string', 'max:32', 'regex:/^[a-z_]+$/'],
            'text' => ['required', 'string', 'max:255'],
        ]);
        VisitEvent::create($data + [
            'visit_id' => $visit->id,
            'user_id' => $this->authUser()?->getAuthIdentifier(),
            'at' => now(),
        ]);

        return response()->json(['ok' => true, 'stored' => true]);
    }

    /** Prescriptions are stored as text (one drug per line) or, on old rows, an array. */
    protected function rxLines($rx): array
    {
        if (is_array($rx)) {
            return collect($rx)->map(fn ($d) => is_array($d) ? trim(($d['drug'] ?? $d['name'] ?? '').' '.($d['dosage'] ?? '')) : (string) $d)
                ->filter()->values()->all();
        }

        return collect(preg_split('/\r?\n/', (string) $rx))->map(fn ($l) => trim($l))->filter()->values()->all();
    }

    /* ── vitals ──────────────────────────────────────────────────────── */

    public function saveVitals(Request $request, Visit $visit): JsonResponse
    {
        if (! $this->canTouchClinical($visit)) {
            return $this->deny();
        }
        if (! $this->visitAcceptsClinicalEdits($visit)) {
            return $this->deny('This visit can no longer be edited.', 422);
        }

        $num = ['nullable', 'numeric', 'min:0', 'max:1000'];
        $data = $request->validate([
            'vitals' => ['required', 'array'],
            'vitals.bp_sys' => $num, 'vitals.bp_dia' => $num, 'vitals.pulse' => $num,
            'vitals.temp' => $num, 'vitals.spo2' => $num, 'vitals.resp' => $num,
            'vitals.glucose' => $num, 'vitals.weight' => $num, 'vitals.height' => $num,
        ]);

        $clean = collect($data['vitals'])
            ->only(['bp_sys', 'bp_dia', 'pulse', 'temp', 'spo2', 'resp', 'glucose', 'weight', 'height'])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (float) $v)
            ->all();
        $clean['taken_at'] = ($visit->vitals['taken_at'] ?? null) ?: now()->toIso8601String();
        $clean['taken_by_user_id'] = $this->authUser()?->getAuthIdentifier();

        $visit->forceFill(['vitals' => $clean])->save();

        return response()->json(['ok' => true, 'vitals' => $clean]);
    }

    /* ── allergies and alerts (patients.allergies / medical_alerts) ─── */

    public function saveAlerts(Request $request, Patient $patient): JsonResponse
    {
        if (! ($this->isAdminUser() || $this->isDoctorUser() || $this->isNurseUser())) {
            return $this->deny();
        }
        // Only a patient seen at a branch this user can reach (the Visit branch
        // scope applies), so an id typed by hand cannot edit anyone else's record.
        if (! Visit::query()->where('patient_id', $patient->id)->exists()) {
            return $this->deny();
        }
        $data = $request->validate([
            'allergies' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'medical_alerts' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $patient->forceFill($data)->save();

        return response()->json(['ok' => true, 'allergies' => $patient->allergies, 'medical_alerts' => $patient->medical_alerts]);
    }

    /* ── invoice / receipt numbers ───────────────────────────────────── */

    /**
     * The catalogue's categories for the Items tab's browse-by-category cards,
     * in the clinic's order. Same audience and clinic scoping as the
     * catalogue search it sits beside (anyone who works visits).
     */
    public function categories(Request $request, Visit $visit): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('view_any_visits'), 403, 'Not authorized to view visits.');
        if (! Schema::hasTable('clinic_catalog_categories')) {
            return response()->json(['categories' => []]);
        }
        $partnerId = $visit->branch?->partner_id;

        $rows = \App\Models\ClinicCatalogCategory::query()
            ->where('is_active', true)
            ->when($partnerId, fn ($w) => $w->where(fn ($w2) => $w2->where('partner_id', $partnerId)->orWhereNull('partner_id')))
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'name_en' => $c->label('en'), 'name_ar' => $c->label('ar')])
            ->values();

        return response()->json(['categories' => $rows]);
    }

    /**
     * Give a printed paper its permanent number. The invoice number belongs
     * to the visit and the receipt number to the payment: asking again
     * returns the same number (a reprint), counted as a copy.
     */
    public function document(Request $request, Visit $visit): JsonResponse
    {
        if (! Schema::hasTable('visit_documents')) {
            return response()->json(['ok' => true, 'number' => null]);
        }
        if (! ($this->canCollectPayment($visit) || $this->canOperateVisit($visit))) {
            return $this->deny();
        }
        $data = $request->validate([
            'kind' => ['required', 'in:invoice,receipt'],
            'payment_id' => ['required_if:kind,receipt', 'nullable', 'integer'],
            'snapshot' => ['nullable', 'array'],
        ]);
        if ($data['kind'] === 'receipt') {
            $belongs = VisitPayment::query()->where('id', $data['payment_id'])->where('visit_id', $visit->id)->exists();
            if (! $belongs) {
                return $this->deny('That payment is not on this visit.', 422);
            }
        }

        $doc = DB::transaction(function () use ($data, $visit) {
            $existing = VisitDocument::query()
                ->where('visit_id', $visit->id)->where('kind', $data['kind'])
                ->when($data['kind'] === 'receipt', fn ($q) => $q->where('payment_id', $data['payment_id']))
                ->lockForUpdate()->first();
            if ($existing) {
                $existing->forceFill(['print_count' => $existing->print_count + 1, 'last_printed_at' => now()])->save();

                return $existing;
            }
            $year = (int) now()->format('Y');
            // Lock the year's rows so two desks printing at once never share a number.
            $seq = (int) VisitDocument::query()->where('kind', $data['kind'])->where('year', $year)->lockForUpdate()->max('seq') + 1;
            $prefix = $data['kind'] === 'invoice' ? 'INV' : 'RC';

            return VisitDocument::create([
                'visit_id' => $visit->id,
                'payment_id' => $data['kind'] === 'receipt' ? $data['payment_id'] : null,
                'kind' => $data['kind'],
                'year' => $year,
                'seq' => $seq,
                'number' => sprintf('%s-%d-%06d', $prefix, $year, $seq),
                'snapshot' => $data['snapshot'] ?? null,
                'first_printed_by_user_id' => $this->authUser()?->getAuthIdentifier(),
                'first_printed_at' => now(),
                'last_printed_at' => now(),
            ]);
        });

        return response()->json([
            'ok' => true,
            'number' => $doc->number,
            'copy' => $doc->print_count > 1,
            'first_printed_at' => $doc->first_printed_at?->toIso8601String(),
        ]);
    }

    /* ── saved order sets ────────────────────────────────────────────── */

    public function orderSets(): JsonResponse
    {
        if (! Schema::hasTable('order_sets')) {
            return response()->json(['ok' => true, 'sets' => []]);
        }
        $uid = $this->authUser()?->getAuthIdentifier();
        $sets = OrderSet::query()
            ->where(fn ($q) => $q->where('owner_user_id', $uid)->orWhere('shared', true))
            ->orderByDesc('uses')->orderBy('name')
            ->limit(100)
            ->get();

        return response()->json(['ok' => true, 'sets' => $sets->map(fn (OrderSet $s) => $this->setJson($s, $uid))->values()]);
    }

    public function storeOrderSet(Request $request): JsonResponse
    {
        if (! ($this->isAdminUser() || $this->isDoctorUser())) {
            return $this->deny();
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'shared' => ['boolean'],
            'drugs' => ['array', 'max:30'],
            'drugs.*.name' => ['required', 'string', 'max:200'],
            'drugs.*.strength' => ['nullable', 'string', 'max:60'],
            'drugs.*.dose' => ['nullable', 'string', 'max:120'],
            'drugs.*.freq' => ['nullable', 'string', 'max:120'],
            'drugs.*.dur' => ['nullable', 'string', 'max:60'],
            'lab_test_ids' => ['array', 'max:40'],
            'lab_test_ids.*' => ['integer', 'exists:lab_tests,id'],
            'items' => ['array', 'max:30'],
            'items.*.type' => ['required', 'in:item,package'],
            'items.*.id' => ['required', 'integer'],
            'follow_up_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $uid = $this->authUser()?->getAuthIdentifier();
        $set = OrderSet::create([
            'owner_user_id' => $uid,
            'name' => $data['name'],
            'shared' => (bool) ($data['shared'] ?? false),
            'drugs' => $data['drugs'] ?? [],
            'lab_test_ids' => $data['lab_test_ids'] ?? [],
            'items' => $data['items'] ?? [],
            'follow_up_days' => $data['follow_up_days'] ?? null,
        ]);

        return response()->json(['ok' => true, 'set' => $this->setJson($set, $uid)]);
    }

    public function useOrderSet(OrderSet $orderSet): JsonResponse
    {
        $orderSet->increment('uses');

        return response()->json(['ok' => true]);
    }

    public function destroyOrderSet(OrderSet $orderSet): JsonResponse
    {
        $uid = $this->authUser()?->getAuthIdentifier();
        if (! $this->isAdminUser() && (int) $orderSet->owner_user_id !== (int) $uid) {
            return $this->deny();
        }
        $orderSet->delete();

        return response()->json(['ok' => true]);
    }

    protected function setJson(OrderSet $s, $uid): array
    {
        return [
            'id' => $s->id, 'name' => $s->name, 'shared' => $s->shared,
            'mine' => (int) $s->owner_user_id === (int) $uid,
            'drugs' => $s->drugs ?? [], 'lab_test_ids' => $s->lab_test_ids ?? [],
            'items' => $s->items ?? [], 'follow_up_days' => $s->follow_up_days,
        ];
    }

    /* ── waiting-room calls ──────────────────────────────────────────── */

    public function call(Visit $visit): JsonResponse
    {
        if (! Schema::hasTable('queue_calls')) {
            return $this->deny('Calling is not set up yet.', 422);
        }
        if (! ($this->canOperateVisit($visit) || $this->isNurseUser())) {
            return $this->deny();
        }
        $visit->loadMissing(['patient', 'doctor', 'room']);

        // The public screen never shows a full name or a file number.
        $parts = preg_split('/\s+/u', trim((string) $visit->patient?->name)) ?: [];
        $display = count($parts) > 1 ? $parts[0].' '.Str::substr(end($parts), 0, 1).'.' : ($parts[0] ?? '—');
        $doctorName = preg_replace('/^\s*(dr\.?\s*|د\.?\s*)/iu', '', (string) $visit->doctor?->name);
        $letter = Str::upper(Str::substr($doctorName ?: 'A', 0, 1));
        $digits = substr(preg_replace('/\D/', '', (string) ($visit->booking_code ?? $visit->id)), -3);
        $roomName = $visit->room?->name;
        if (is_array($roomName)) {
            $roomName = $roomName[app()->getLocale()] ?? reset($roomName);
        }

        $call = QueueCall::create([
            'visit_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'ticket' => $letter.'-'.str_pad($digits ?: (string) $visit->id, 3, '0', STR_PAD_LEFT),
            'display_name' => Str::limit($display, 78, ''),
            'room' => $roomName,
            'called_by_user_id' => $this->authUser()?->getAuthIdentifier(),
            'called_at' => now(),
        ]);

        // WhatsApp the patient too — off unless WHATSAPP_QUEUE_CALL_ENABLED and
        // the Meta template is approved; never throws (CallNotifier).
        $whatsapp = app(\App\Services\Workspace\CallNotifier::class)->notify($visit, (string) ($roomName ?? ''));

        return response()->json([
            'ok' => true,
            'whatsapp' => $whatsapp,
            'call' => $this->callJson($call),
            'count_today' => QueueCall::where('visit_id', $visit->id)->where('called_at', '>=', today())->count(),
        ]);
    }

    public function calls(Request $request): JsonResponse
    {
        if (! Schema::hasTable('queue_calls')) {
            return response()->json(['ok' => true, 'calls' => []]);
        }
        // whereHas('visit') runs the Visit branch scope, so a screen only
        // ever shows its own branch's calls.
        $calls = QueueCall::query()
            ->where('called_at', '>=', today())
            ->whereHas('visit')
            ->orderByDesc('called_at')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        return response()->json(['ok' => true, 'calls' => $calls->map(fn ($c) => $this->callJson($c))]);
    }

    protected function callJson(QueueCall $c): array
    {
        return [
            'id' => $c->id, 'visit_id' => $c->visit_id, 'ticket' => $c->ticket,
            'name' => $c->display_name, 'room' => $c->room, 'at' => $c->called_at?->toIso8601String(),
        ];
    }
}
