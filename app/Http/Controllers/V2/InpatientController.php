<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Inpatient\Admission;
use App\Models\Inpatient\AdmissionCharge;
use App\Models\Inpatient\AdmissionRound;
use App\Models\Inpatient\Bed;
use App\Models\Inpatient\Ward;
use App\Models\Patient;
use App\Services\Inpatient\AdmissionService;
use App\Services\Inpatient\BedAssignmentService;
use App\Support\ResolvesAccessibleClinics;
use App\Support\VisitAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InpatientController extends Controller
{
    use VisitAuthorization;
    use ResolvesAccessibleClinics;

    public function __construct(
        protected BedAssignmentService $beds,
        protected AdmissionService $admissions,
    ) {}

    /* ============================================================
     |  Inertia pages
     |============================================================*/

    /**
     * Visual bed board: wards as columns of bed cards, color-coded by
     * status. The whole inpatient workflow runs from here — click an
     * available bed to admit, an occupied bed to open the admission
     * sheet, a cleaning bed to mark it free again.
     */
    public function board(Request $request): Response
    {
        $this->ensureCanView();

        $wards = Ward::query()
            ->where('is_active', true)
            ->with(['beds' => fn ($q) => $q->where('is_active', true)->orderBy('code')])
            ->orderBy('name')
            ->get();

        $occupiedIds = Bed::query()
            ->where('status', Bed::STATUS_OCCUPIED)
            ->pluck('id');

        // Map bed_id -> current admission (lightweight payload for cards).
        $currentByBed = Admission::query()
            ->where('status', Admission::STATUS_ACTIVE)
            ->whereHas('currentBedStay', fn ($q) => $q->whereIn('bed_id', $occupiedIds))
            ->with(['patient:id,name,phone,dob,gender', 'admittingDoctor:id,name', 'currentBedStay'])
            ->get()
            ->keyBy(fn (Admission $a) => $a->currentBedStay?->bed_id);

        $payload = $wards->map(function (Ward $w) use ($currentByBed) {
            return [
                'id' => $w->id,
                'name' => $w->name,
                'code' => $w->code,
                'type' => $w->ward_type,
                'daily_rate' => (float) $w->daily_rate,
                'beds' => $w->beds->map(fn (Bed $b) => [
                    'id' => $b->id,
                    'code' => $b->code,
                    'status' => $b->status,
                    'daily_rate' => $b->effectiveDailyRate(),
                    'features' => $b->features ?? [],
                    'admission' => $currentByBed->get($b->id) ? [
                        'id' => $currentByBed[$b->id]->id,
                        'admission_code' => $currentByBed[$b->id]->admission_code,
                        'patient_name' => $currentByBed[$b->id]->patient?->name,
                        'doctor_name' => $currentByBed[$b->id]->admittingDoctor?->name,
                        'admitted_at' => optional($currentByBed[$b->id]->admitted_at)->toIso8601String(),
                    ] : null,
                ]),
            ];
        });

        return Inertia::render('Inpatient/BedBoard', [
            'wards' => $payload,
            'can_manage' => $this->canManageInpatient(),
            'can_set_bed_status' => $this->canSetBedStatus(),
            'counts' => [
                'total' => Bed::query()->where('is_active', true)->count(),
                'occupied' => Bed::query()->where('is_active', true)->where('status', Bed::STATUS_OCCUPIED)->count(),
                'available' => Bed::query()->where('is_active', true)->where('status', Bed::STATUS_AVAILABLE)->count(),
                'cleaning' => Bed::query()->where('is_active', true)->where('status', Bed::STATUS_CLEANING)->count(),
                'maintenance' => Bed::query()->where('is_active', true)->where('status', Bed::STATUS_MAINTENANCE)->count(),
                'active_admissions' => Admission::query()->where('status', Admission::STATUS_ACTIVE)->count(),
            ],
        ]);
    }

    /**
     * Admissions list — active + recent discharged, with quick filters.
     */
    /** Styled .xlsx export of admissions (mirrors the status filter). */
    public function admissionsExport(Request $request)
    {
        $this->ensureCanView();
        $status = $request->string('status')->toString() ?: 'active';

        $query = Admission::query()->with(['patient:id,name,phone', 'admittingDoctor:id,name', 'currentBedStay.bed:id,code'])->orderByDesc('admitted_at');
        if ($status === 'active') { $query->where('status', Admission::STATUS_ACTIVE); }
        elseif ($status !== 'all') { $query->where('status', $status); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query,
                ['Code', 'Status', 'Patient', 'Phone', 'Doctor', 'Bed', 'Admitted', 'Discharged'],
                fn ($a) => [$a->admission_code, $a->status, $a->patient?->name, $a->patient?->phone, $a->admittingDoctor?->name, $a->currentBedStay?->bed?->code, optional($a->admitted_at)->format('Y-m-d H:i'), optional($a->discharged_at)->format('Y-m-d H:i')],
                'Admissions',
                app()->getLocale() === 'ar',
            ),
            'admissions-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function admissionsIndex(Request $request): Response
    {
        $this->ensureCanView();

        $status = $request->string('status')->toString() ?: 'active';
        $q = Admission::query()
            ->with(['patient:id,name,phone', 'admittingDoctor:id,name', 'currentBedStay.bed:id,code'])
            ->orderByDesc('admitted_at');

        if ($status === 'active') {
            $q->where('status', Admission::STATUS_ACTIVE);
        } elseif ($status === 'all') {
            // no filter
        } else {
            $q->where('status', $status);
        }

        $rows = $q->limit(200)->get()->map(fn (Admission $a) => $this->transformListRow($a));

        return Inertia::render('Inpatient/Admissions', [
            'rows' => $rows,
            'filters' => ['status' => $status],
            'counts' => [
                'active' => Admission::query()->where('status', Admission::STATUS_ACTIVE)->count(),
                'discharged' => Admission::query()->where('status', Admission::STATUS_DISCHARGED)->count(),
            ],
        ]);
    }

    /* ============================================================
     |  JSON endpoints for the bed board / admission sheet
     |============================================================*/

    public function show(Admission $admission): JsonResponse
    {
        $this->ensureCanView();
        return response()->json($this->transformAdmission($admission));
    }

    /**
     * Print-friendly HTML discharge summary. Opens in a new tab; the
     * browser's print dialog (Cmd/Ctrl-P) or "Save as PDF" handles the
     * rest. No PDF library dependency.
     */
    public function printSummary(Admission $admission)
    {
        $this->ensureCanView();
        $admission->load([
            'patient', 'admittingDoctor', 'branch',
            'bedStays.bed.ward',
            'charges' => fn ($q) => $q->orderBy('charge_date'),
            'rounds.doctor',
            'finalVisit',
            'dischargedBy',
        ]);
        return response()->view('inpatient.discharge-summary', ['admission' => $admission]);
    }

    public function lookupPatients(Request $request): JsonResponse
    {
        $this->ensureCanManage();
        $term = trim($request->string('q')->toString());
        $q = Patient::query()->orderBy('name')->limit(20);

        // Clinic isolation: patients carry partner_id (no branch_id), so scope
        // to the user's clinic(s). Global admins see all.
        $partnerIds = $this->accessiblePartnerIds();
        if ($partnerIds !== null) {
            $q->whereIn('partner_id', $partnerIds ?: [0]);
        }

        // Doctors can only admit someone checked in today — so only surface
        // those patients in the picker.
        if ($this->isDoctorUser() && ! $this->isGlobalAdmin()) {
            $q->whereIn('id', $this->todayCheckedInPatientIds() ?: [0]);
        }

        if ($term !== '') {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
        }
        return response()->json(['patients' => $q->get(['id', 'name', 'phone', 'gender', 'dob'])]);
    }

    /**
     * Doctors selectable as the admitting/attending doctor.
     *
     * Optionally narrowed to a branch: an admission belongs to a bed at one
     * branch, so an admin (who is not limited by BelongsToBranchScope) would
     * otherwise be offered every doctor in the group and could attach a doctor
     * who does not work where the patient is admitted.
     */
    public function lookupDoctors(Request $request): JsonResponse
    {
        $this->ensureCanManage();

        $branchId = $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null;

        return response()->json([
            'doctors' => Doctor::query()
                ->where('is_active', true)
                ->atBranch($branchId)
                ->orderBy('name')
                ->get(['id', 'name', 'specialty']),
        ]);
    }

    public function lookupBranches(Request $request): JsonResponse
    {
        $this->ensureCanManage();

        return response()->json(['branches' => $this->accessibleBranches()->values()]);
    }

    public function lookupAvailableBeds(Request $request): JsonResponse
    {
        $this->ensureCanManage();
        $rows = Bed::query()
            ->with('ward:id,name,daily_rate')
            ->where('status', Bed::STATUS_AVAILABLE)
            ->where('is_active', true)
            ->orderBy('ward_id')
            ->orderBy('code')
            ->get()
            ->map(fn (Bed $b) => [
                'id' => $b->id,
                'code' => $b->code,
                'ward_name' => $b->ward?->name,
                'daily_rate' => $b->effectiveDailyRate(),
            ]);
        return response()->json(['beds' => $rows]);
    }

    public function admit(Request $request): JsonResponse
    {
        $this->ensureCanManage();

        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'admitting_doctor_id' => ['required', 'exists:doctors,id'],
            // Optional: the server derives it from the target bed (or the user's
            // own branch) so the client never has to know the branch.
            'branch_id' => ['nullable', 'exists:branches,id'],
            'admission_reason' => ['required', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'expected_discharge_at' => ['nullable', 'date', 'after_or_equal:today'],
            'bed_id' => ['nullable', 'exists:beds,id'],
        ]);

        $bed = ! empty($data['bed_id']) ? Bed::query()->findOrFail($data['bed_id']) : null;

        // Branch auto-derive: explicit → the bed's branch → the user's branch.
        $branchId = $data['branch_id'] ?? $bed?->branch_id ?? $this->resolveActingBranchId();
        if (! $branchId) {
            return response()->json(['ok' => false, 'error' => 'Could not determine the branch — pick a bed first.'], 422);
        }
        $branch = \App\Models\Branch::query()->findOrFail($branchId);

        // Clinic / branch isolation — never admit across clinics.
        $patient = Patient::query()->findOrFail($data['patient_id']);
        $partnerIds = $this->accessiblePartnerIds();
        if ($partnerIds !== null) {
            if ($patient->partner_id && ! in_array($patient->partner_id, $partnerIds, true)) {
                return response()->json(['ok' => false, 'error' => 'This patient belongs to another clinic.'], 403);
            }
            if (! in_array($branch->partner_id, $partnerIds, true)) {
                return response()->json(['ok' => false, 'error' => 'You cannot admit into this branch.'], 403);
            }
        }

        // Doctors may only admit a patient who was checked in today.
        if ($this->isDoctorUser() && ! $this->isGlobalAdmin()
            && ! in_array($patient->id, $this->todayCheckedInPatientIds(), true)) {
            return response()->json(['ok' => false, 'error' => 'You can only admit a patient who was checked in today.'], 422);
        }

        try {
            $admission = $this->admissions->admit([
                'patient_id' => $data['patient_id'],
                'admitting_doctor_id' => $data['admitting_doctor_id'],
                'branch_id' => $branch->id,
                'partner_id' => $branch->partner_id,
                'admission_reason' => $data['admission_reason'],
                'diagnosis' => $data['diagnosis'] ?? null,
                'expected_discharge_at' => $data['expected_discharge_at'] ?? null,
            ], $bed, $request->user());

            return response()->json(['ok' => true, 'admission' => $this->transformAdmission($admission)]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function assignBed(Request $request, Admission $admission): JsonResponse
    {
        $this->ensureCanManage();
        $data = $request->validate(['bed_id' => ['required', 'exists:beds,id']]);

        try {
            $this->beds->assign($admission, Bed::query()->findOrFail($data['bed_id']), $request->user(), 'Manual assignment');
            return response()->json(['ok' => true, 'admission' => $this->transformAdmission($admission->fresh())]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function transfer(Request $request, Admission $admission): JsonResponse
    {
        $this->ensureCanManage();
        $data = $request->validate([
            'bed_id' => ['required', 'exists:beds,id'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $this->beds->transfer($admission, Bed::query()->findOrFail($data['bed_id']), $request->user(), $data['reason']);
            return response()->json(['ok' => true, 'admission' => $this->transformAdmission($admission->fresh())]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function discharge(Request $request, Admission $admission): JsonResponse
    {
        $this->ensureCanManage();
        $data = $request->validate([
            'summary' => ['required', 'string', 'max:5000'],
            'final_status' => ['nullable', 'in:discharged,lama,transferred_out,expired'],
        ]);

        try {
            $result = $this->admissions->discharge(
                $admission, $request->user(), $data['summary'], $data['final_status'] ?? Admission::STATUS_DISCHARGED
            );
            return response()->json([
                'ok' => true,
                'admission' => $this->transformAdmission($result['admission']),
                'final_visit_id' => $result['final_visit']->id,
                'total' => $result['total'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function addRound(Request $request, Admission $admission): JsonResponse
    {
        $this->ensureCanManage();
        $data = $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'round_date' => ['nullable', 'date'],
            'vitals' => ['nullable', 'array'],
            'progress_notes' => ['nullable', 'string', 'max:5000'],
            'med_changes' => ['nullable', 'string', 'max:2000'],
            'next_steps' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $round = AdmissionRound::create([
                'admission_id' => $admission->id,
                'doctor_id' => $data['doctor_id'],
                'round_date' => $data['round_date'] ?? today()->toDateString(),
                'vitals' => $data['vitals'] ?? null,
                'progress_notes' => $data['progress_notes'] ?? null,
                'med_changes' => $data['med_changes'] ?? null,
                'next_steps' => $data['next_steps'] ?? null,
                'created_by_user_id' => $request->user()?->id,
            ]);

            return response()->json(['ok' => true, 'round' => $round->fresh()]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(['ok' => false, 'error' => 'A round for this doctor on this date already exists. Edit it from the admission detail.'], 422);
        }
    }

    public function addCharge(Request $request, Admission $admission): JsonResponse
    {
        $this->ensureCanManage();
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'charge_date' => ['nullable', 'date'],
        ]);

        $charge = AdmissionCharge::create([
            'admission_id' => $admission->id,
            'charge_date' => $data['charge_date'] ?? today(),
            'description' => $data['description'],
            'amount' => $data['amount'],
            'source' => AdmissionCharge::SOURCE_MANUAL,
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(['ok' => true, 'charge' => $charge]);
    }

    /**
     * Flip a bed between non-occupied housekeeping states. Refuses to
     * touch occupied beds — those must go through discharge/transfer.
     */
    public function setBedStatus(Request $request, Bed $bed): JsonResponse
    {
        if (! $this->canSetBedStatus()) {
            abort(403);
        }
        $data = $request->validate([
            'status' => ['required', 'in:available,cleaning,maintenance,reserved'],
        ]);
        if ($bed->status === Bed::STATUS_OCCUPIED) {
            return response()->json(['ok' => false, 'error' => 'Bed is occupied — discharge or transfer the patient first.'], 422);
        }
        $bed->update(['status' => $data['status']]);
        return response()->json(['ok' => true, 'bed' => $bed->fresh()->only(['id', 'code', 'status'])]);
    }

    /* ============================================================
     |  Helpers
     |============================================================*/

    protected function ensureCanView(): void
    {
        if (! ($this->isAdminUser() || $this->isReceptionUser() || $this->isDoctorUser())) {
            abort(403);
        }
    }

    protected function ensureCanManage(): void
    {
        if (! $this->canManageInpatient()) {
            abort(403);
        }
    }

    /**
     * Admins, clinic_admins and doctors can run the inpatient workflow
     * (admit / transfer / discharge / log rounds). Reception is read-only
     * on admissions but can flip non-occupied bed states.
     */
    protected function canManageInpatient(): bool
    {
        return $this->isAdminUser() || $this->isDoctorUser();
    }

    protected function canSetBedStatus(): bool
    {
        return $this->isAdminUser() || $this->isReceptionUser();
    }

    /**
     * Best-effort "which branch is this user acting in" when no bed (and thus
     * no ward→branch) pins it down: their doctor profile's branch, else their
     * single staff branch. Null when ambiguous (multi-branch, no bed).
     */
    protected function resolveActingBranchId(): ?int
    {
        $uid = (int) (auth()->id() ?? 0);

        $docBranch = DB::table('doctors')->where('user_id', $uid)->whereNotNull('branch_id')->value('branch_id');
        if ($docBranch) {
            return (int) $docBranch;
        }
        $staff = DB::table('branch_user')->where('user_id', $uid)->pluck('branch_id');

        return $staff->count() === 1 ? (int) $staff->first() : null;
    }

    /**
     * Patient ids checked in today. The Visit query is already narrowed by
     * BelongsToBranchScope (branch + doctor for doctor users), so for a doctor
     * this is exactly "patients I'm seeing today".
     */
    protected function todayCheckedInPatientIds(): array
    {
        return \App\Models\Visit::query()
            ->whereNotNull('checked_in_at')
            ->whereDate('checked_in_at', today())
            ->pluck('patient_id')
            ->unique()
            ->values()
            ->all();
    }

    protected function transformListRow(Admission $a): array
    {
        return [
            'id' => $a->id,
            'admission_code' => $a->admission_code,
            'status' => $a->status,
            'admitted_at' => optional($a->admitted_at)->toIso8601String(),
            'discharged_at' => optional($a->discharged_at)->toIso8601String(),
            'patient' => $a->patient ? ['id' => $a->patient->id, 'name' => $a->patient->name, 'phone' => $a->patient->phone] : null,
            'doctor' => $a->admittingDoctor ? ['id' => $a->admittingDoctor->id, 'name' => $a->admittingDoctor->name] : null,
            'bed' => $a->currentBedStay?->bed ? ['id' => $a->currentBedStay->bed->id, 'code' => $a->currentBedStay->bed->code] : null,
        ];
    }

    protected function transformAdmission(Admission $a): array
    {
        $a->load([
            'patient', 'admittingDoctor', 'branch',
            'currentBedStay.bed.ward',
            'bedStays.bed.ward',
            'charges' => fn ($q) => $q->orderBy('charge_date'),
            'rounds.doctor',
            'finalVisit',
        ]);

        $bedDaysTotal = (float) $a->charges->sum('amount');

        return [
            'id' => $a->id,
            'admission_code' => $a->admission_code,
            'status' => $a->status,
            'admitted_at' => optional($a->admitted_at)->toIso8601String(),
            'discharged_at' => optional($a->discharged_at)->toIso8601String(),
            'expected_discharge_at' => optional($a->expected_discharge_at)->toIso8601String(),
            'admission_reason' => $a->admission_reason,
            'diagnosis' => $a->diagnosis,
            'discharge_summary' => $a->discharge_summary,
            'patient' => $a->patient ? [
                'id' => $a->patient->id,
                'name' => $a->patient->name,
                'phone' => $a->patient->phone,
                'gender' => $a->patient->gender,
                'dob' => $a->patient->dob,
            ] : null,
            'doctor' => $a->admittingDoctor ? ['id' => $a->admittingDoctor->id, 'name' => $a->admittingDoctor->name] : null,
            'branch' => $a->branch ? ['id' => $a->branch->id, 'name' => $a->branch->getTranslation('name', app()->getLocale(), true)] : null,
            'current_bed' => $a->currentBedStay && $a->currentBedStay->bed ? [
                'id' => $a->currentBedStay->bed->id,
                'code' => $a->currentBedStay->bed->code,
                'ward' => $a->currentBedStay->bed->ward?->name,
                'daily_rate' => (float) $a->currentBedStay->daily_rate,
                'assigned_at' => optional($a->currentBedStay->assigned_at)->toIso8601String(),
            ] : null,
            'bed_stays' => $a->bedStays->map(fn ($s) => [
                'id' => $s->id,
                'bed_code' => $s->bed?->code,
                'ward_name' => $s->bed?->ward?->name,
                'assigned_at' => optional($s->assigned_at)->toIso8601String(),
                'released_at' => optional($s->released_at)->toIso8601String(),
                'daily_rate' => (float) $s->daily_rate,
                'reason' => $s->reason_for_change,
            ]),
            'charges' => $a->charges->map(fn ($c) => [
                'id' => $c->id,
                'charge_date' => optional($c->charge_date)->toDateString(),
                'description' => $c->description,
                'amount' => (float) $c->amount,
                'source' => $c->source,
            ]),
            'rounds' => $a->rounds->map(fn ($r) => [
                'id' => $r->id,
                'round_date' => optional($r->round_date)->toDateString(),
                'doctor_id' => $r->doctor_id,
                'doctor_name' => $r->doctor?->name,
                'vitals' => $r->vitals ?? [],
                'progress_notes' => $r->progress_notes,
                'med_changes' => $r->med_changes,
                'next_steps' => $r->next_steps,
            ]),
            'bed_days_total' => $bedDaysTotal,
            'final_visit_id' => $a->final_visit_id,
            'permissions' => [
                'can_manage' => $this->canManageInpatient(),
                'can_assign_bed' => $this->canManageInpatient() && $a->isActive() && ! $a->currentBedStay,
                'can_transfer' => $this->canManageInpatient() && $a->isActive() && $a->currentBedStay,
                'can_discharge' => $this->canManageInpatient() && $a->isActive(),
            ],
        ];
    }
}
