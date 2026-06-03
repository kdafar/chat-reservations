<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Patient;
use App\Models\PatientFile;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PatientsController extends Controller
{
    use ResolvesAccessibleClinics;

    /**
     * The patient directory exposes PHI, so every entry point requires the
     * view permission (granted to clinical + management roles in the seeder).
     */
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_patients')) {
            abort(403, 'Not authorized to view patients.');
        }
    }

    /**
     * Patients list — table view with search + filter + paginated.
     * Doctor users only see patients whose bookings they could access via
     * the existing BelongsToBranchScope.
     */
    /** Stream selected patients as CSV (bulk export). Not an Inertia response. */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));
        $query = Patient::query()->when($ids, fn ($q) => $q->whereIn('id', $ids))->orderBy('name');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query,
                ['ID', 'Name', 'Phone', 'Civil ID', 'DOB', 'Gender', 'Blood group'],
                fn ($p) => [$p->id, $p->name, $p->phone, $p->civil_id, (string) $p->dob, $p->gender, $p->blood_group],
                'Patients',
                app()->getLocale() === 'ar',
            ),
            'patients-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    /** Create a patient (v2 replacement for the Filament create form). */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('create_patients')) {
            abort(403, 'Not authorized to create patients.');
        }
        $data = $this->validatedPatient($request);
        $patient = Patient::create($data);
        return redirect()->route('v2.patients.show', $patient)
            ->with('flash', ['type' => 'success', 'message' => 'Patient created.']);
    }

    /** Edit a patient (v2 replacement for the Filament edit form). */
    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('update_patients')) {
            abort(403, 'Not authorized to update patients.');
        }
        $patient->update($this->validatedPatient($request, $patient));
        return back()->with('flash', ['type' => 'success', 'message' => 'Patient updated.']);
    }

    /** Partner options for the create/edit form (scoped to the user's clinics). */
    public function partnerOptions(): array
    {
        $partnerIds = $this->accessiblePartnerIds(); // null = global admin (all)

        return Partner::query()
            ->when($partnerIds !== null, fn ($q) => $q->whereIn('id', $partnerIds ?: [0]))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => is_array($p->name) ? ($p->name[app()->getLocale()] ?? $p->name['en'] ?? reset($p->name)) : $p->name])
            ->all();
    }

    protected function validatedPatient(Request $request, ?Patient $patient = null): array
    {
        // Clinic isolation: a non-admin can only create/keep a patient inside a
        // clinic they belong to — never default to (or be tricked into) another
        // clinic's partner_id.
        $accessible = $this->accessiblePartnerIds(); // null = global admin
        $requested = $request->input('partner_id') ?: ($patient?->partner_id ?: null);
        if ($accessible !== null && (! $requested || ! in_array((int) $requested, array_map('intval', $accessible), true))) {
            $requested = $accessible[0] ?? null;
        }
        $partnerId = $requested ?: Partner::query()->value('id');

        $data = $request->validate([
            'partner_id' => ['nullable', 'integer', Rule::exists('partners', 'id')],
            'name' => ['required', 'string', 'max:191'],
            'phone' => [
                'required', 'string', 'max:32',
                Rule::unique('patients', 'phone')->where(fn ($q) => $q->where('partner_id', $partnerId))->ignore($patient?->id)->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email', 'max:191'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'civil_id' => ['nullable', 'string', 'max:32'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'medical_alerts' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['partner_id'] = $partnerId;
        return $data;
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $filters = $this->normalizeFilters($request);

        return Inertia::render('Patients/Index', [
            'filters' => $filters,
            'page' => $this->queryPatients($filters),
            'counts' => $this->statusCounts($filters),
            'partners' => $this->partnerOptions(),
        ]);
    }

    public function quickView(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeAccess($request);
        $visitCount = Visit::query()->where('patient_id', $patient->id)->count();
        $lastVisit = Visit::query()->where('patient_id', $patient->id)
            ->orderByDesc('checked_in_at')->first(['id', 'status', 'checked_in_at', 'diagnosis', 'doctor_id']);
        $upcomingBookings = \App\Models\Booking::query()
            ->where('patient_id', $patient->id)
            ->whereDate('res_date', '>=', today())
            ->whereIn('status', ['confirmed', 'pending'])
            ->orderBy('res_date')->orderBy('res_time')
            ->limit(5)
            ->get(['id', 'booking_code', 'res_date', 'res_time', 'doctor_id', 'status', 'checked_in_at']);

        $age = null;
        if ($patient->dob) { try { $age = Carbon::parse($patient->dob)->age; } catch (\Throwable) {} }

        $totalPaid = (float) VisitPayment::query()
            ->whereIn('visit_id', Visit::where('patient_id', $patient->id)->pluck('id'))
            ->where('status', 'paid')
            ->sum('amount');

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'phone' => $patient->phone,
                'email' => $patient->email,
                'civil_id' => $patient->civil_id,
                'gender' => $patient->gender,
                'age' => $age,
                'dob' => optional($patient->dob)->toDateString(),
                'allergies' => $patient->allergies,
                'blood_group' => $patient->blood_group,
                'medical_alerts' => $patient->medical_alerts,
                'notes' => $patient->notes,
                'created_at' => optional($patient->created_at)->toIso8601String(),
            ],
            'totals' => [
                'visits' => $visitCount,
                'total_paid' => $totalPaid,
            ],
            'last_visit' => $lastVisit ? [
                'id' => $lastVisit->id,
                'status' => $lastVisit->status,
                'date' => optional($lastVisit->checked_in_at)->toIso8601String(),
                'diagnosis' => $lastVisit->diagnosis,
            ] : null,
            'upcoming' => $upcomingBookings->map(fn ($b) => [
                'id' => $b->id,
                'booking_code' => $b->booking_code,
                'date' => optional($b->res_date)->toDateString(),
                'time' => $b->res_time,
                'status' => $b->status,
                'checked_in' => ! is_null($b->checked_in_at),
            ]),
        ]);
    }

    protected function normalizeFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'gender' => in_array($request->input('gender'), ['male', 'female'], true) ? $request->input('gender') : null,
            'has_phone' => $request->input('has_phone'),
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => 20,
        ];
    }

    protected function baseQuery(array $f)
    {
        $q = Patient::query();

        if ($f['q'] !== '' && mb_strlen($f['q']) >= 2) {
            $like = '%'.$f['q'].'%';
            $q->where(function ($w) use ($like, $f) {
                $w->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('civil_id', 'like', $like)
                    ->orWhere('id', $f['q']);
            });
        }
        if ($f['gender']) $q->where('gender', $f['gender']);
        if ($f['has_phone'] === 'yes') $q->whereNotNull('phone')->where('phone', '!=', '');
        if ($f['has_phone'] === 'no') $q->where(fn ($w) => $w->whereNull('phone')->orWhere('phone', ''));

        // Non-admin: limit to patients with a booking the user can access.
        // (Booking has BelongsToBranchScope; pluck through it.)
        $user = auth()->user();
        $isAdmin = $user && method_exists($user, 'hasRole')
            && ($user->hasRole('admin') || $user->hasRole('super_admin'));
        if (! $isAdmin) {
            $accessibleIds = \App\Models\Booking::query()
                ->whereNotNull('patient_id')
                ->distinct()
                ->pluck('patient_id');
            $q->whereIn('id', $accessibleIds);
        }

        return $q;
    }

    protected function queryPatients(array $f): array
    {
        $p = $this->baseQuery($f)
            ->withCount(['visits as visit_count'])
            ->orderByDesc('id')
            ->paginate($f['per_page'], ['*'], 'page', $f['page'])
            ->withQueryString();

        return [
            'data' => collect($p->items())->map(fn (Patient $pt) => $this->transformRow($pt))->values(),
            'meta' => [
                'current_page' => $p->currentPage(),
                'last_page' => $p->lastPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
                'from' => $p->firstItem(),
                'to' => $p->lastItem(),
            ],
        ];
    }

    protected function statusCounts(array $f): array
    {
        $base = $this->baseQuery(array_merge($f, ['gender' => null, 'has_phone' => null]));
        return [
            'total' => (clone $base)->count(),
            'male' => (clone $base)->where('gender', 'male')->count(),
            'female' => (clone $base)->where('gender', 'female')->count(),
            'no_phone' => (clone $base)->where(fn ($w) => $w->whereNull('phone')->orWhere('phone', ''))->count(),
        ];
    }

    protected function transformRow(Patient $p): array
    {
        $age = null;
        if ($p->dob) { try { $age = Carbon::parse($p->dob)->age; } catch (\Throwable) {} }

        return [
            'id' => $p->id,
            'name' => $p->name,
            'phone' => $p->phone,
            'email' => $p->email,
            'civil_id' => $p->civil_id,
            'gender' => $p->gender,
            'age' => $age,
            'allergies' => $p->allergies,
            'visit_count' => $p->visit_count ?? 0,
        ];
    }

    public function show(Request $request, Patient $patient): Response
    {
        $this->authorizeAccess($request);
        $visits = Visit::query()
            ->with(['doctor', 'branch'])
            ->where('patient_id', $patient->id)
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $payments = VisitPayment::query()
            ->whereIn('visit_id', $visits->pluck('id'))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $totals = [
            'total_visits' => $visits->count(),
            'completed' => $visits->where('status', 'completed')->count(),
            'no_shows' => $visits->where('status', 'no_show')->count(),
            'total_paid' => (float) $payments->where('status', 'paid')->sum('amount'),
            'open_balance' => (float) $visits->sum(fn ($v) => max(0, (float) ($v->fees_total ?? 0) + (float) ($v->items_price_total ?? 0) + (float) ($v->packages_price_total ?? 0) - (float) ($v->discount_total ?? 0) - $this->paidForVisit($v, $payments))),
            'last_visit_at' => optional($visits->first()?->checked_in_at)->toIso8601String(),
        ];

        $user = $request->user();
        $canUpload = $user?->can('patient_files_upload') ?? false;
        $canDelete = ($user && method_exists($user, 'hasRole')
            && $user->hasRole(['admin', 'super_admin', 'clinic_admin']))
            || ($user?->can('patient_files_delete') ?? false);
        $canView = $user?->can('patient_files_view') ?? false;

        $files = $canView
            ? PatientFile::query()
                ->where('patient_id', $patient->id)
                ->with(['uploadedBy:id,name', 'visit:id'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (PatientFile $f) => [
                    'id' => $f->id,
                    'patient_id' => $f->patient_id,
                    'visit_id' => $f->visit_id,
                    'category' => $f->category,
                    'original_filename' => $f->original_filename,
                    'mime_type' => $f->mime_type,
                    'size_bytes' => (int) $f->size_bytes,
                    'display_size' => $f->display_size,
                    'notes' => $f->notes,
                    'uploaded_by' => $f->uploadedBy?->name,
                    'created_at' => optional($f->created_at)->toIso8601String(),
                    'download_url' => route('admin.patient-files.download', ['patientFile' => $f->id]),
                    'view_url' => route('admin.patient-files.download', ['patientFile' => $f->id, 'inline' => 1]),
                    'is_image' => str_starts_with((string) $f->mime_type, 'image/'),
                    'is_pdf' => $f->mime_type === 'application/pdf',
                ])
                ->values()
            : collect();

        return Inertia::render('Patients/Profile', [
            'patient' => $this->transformPatient($patient),
            'partners' => $this->partnerOptions(),
            'visits' => $visits->map(fn (Visit $v) => $this->transformVisit($v, $payments))->values(),
            'payments' => $payments->map(fn (VisitPayment $p) => $this->transformPayment($p))->values(),
            'totals' => $totals,
            'files' => $files,
            'visitOptions' => $visits->map(fn (Visit $v) => [
                'id' => $v->id,
                'label' => '#'.$v->id.' — '.(optional($v->checked_in_at)->format('Y-m-d') ?? optional($v->created_at)->format('Y-m-d') ?? '—'),
            ])->values(),
            'permissions' => [
                'files_view' => $canView,
                'files_upload' => $canUpload,
                'files_delete' => $canDelete,
            ],
        ]);
    }

    protected function transformPatient(Patient $p): array
    {
        $age = null;
        if ($p->dob) {
            try { $age = Carbon::parse($p->dob)->age; } catch (\Throwable) {}
        }

        return [
            'id' => $p->id,
            'partner_id' => $p->partner_id,
            'name' => $p->name,
            'phone' => $p->phone,
            'email' => $p->email,
            'civil_id' => $p->civil_id,
            'gender' => $p->gender,
            'dob' => optional($p->dob)->toDateString(),
            'age' => $age,
            'allergies' => $p->allergies,
            'blood_group' => $p->blood_group,
            'medical_alerts' => $p->medical_alerts,
            'notes' => $p->notes,
            'created_at' => optional($p->created_at)->toIso8601String(),
        ];
    }

    protected function paidForVisit(Visit $v, $payments): float
    {
        return (float) $payments->where('visit_id', $v->id)->where('status', 'paid')->sum('amount');
    }

    protected function transformVisit(Visit $v, $payments): array
    {
        $gross = (float) ($v->fees_total ?? 0) + (float) ($v->items_price_total ?? 0) + (float) ($v->packages_price_total ?? 0);
        $discount = (float) ($v->discount_total ?? 0);
        $paid = $this->paidForVisit($v, $payments);

        return [
            'id' => $v->id,
            'status' => $v->status,
            'booking_code' => $v->booking_code,
            'checked_in_at' => optional($v->checked_in_at)->toIso8601String(),
            'completed_at' => optional($v->completed_at)->toIso8601String(),
            'diagnosis' => $v->diagnosis,
            'chief_complaint' => $v->chief_complaint,
            'doctor_name' => $v->doctor?->name,
            'branch_name' => $v->branch?->getTranslation('name', app()->getLocale(), true),
            'totals' => [
                'gross' => $gross,
                'discount' => $discount,
                'net' => max(0, $gross - $discount),
                'paid' => $paid,
                'balance' => max(0, $gross - $discount - $paid),
            ],
        ];
    }

    protected function transformPayment(VisitPayment $p): array
    {
        return [
            'id' => $p->id,
            'amount' => (float) $p->amount,
            'method' => $p->method,
            'kind' => $p->kind,
            'status' => $p->status,
            'reference_no' => $p->reference_no,
            'paid_at' => optional($p->paid_at)->toIso8601String(),
            'visit_id' => $p->visit_id,
        ];
    }
}
