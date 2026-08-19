<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Partner;
use App\Models\User;
use App\Services\Clinic\WorkingHoursService;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Doctors — v2 replacement for Filament DoctorResource.
 *
 * Access: gated on `view_any_doctors` (admin/clinic_admin by default).
 * Branch scoping is automatic via the Doctor model's BelongsToBranchScope.
 */
class DoctorsController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_doctors')) {
            abort(403, 'Not authorized to view doctors.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_doctors');
    }

    /** Styled .xlsx export of the doctor roster (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $branchId = $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null;
        $active = $request->input('active', 'all');

        $query = Doctor::query()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])
            ->with(['branch:id,name']);
        if ($q !== '') {
            $query->where(fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")->orWhere('license_number', 'like', "%{$q}%")->orWhere('specialty', 'like', "%{$q}%"));
        }
        if ($branchId !== null) { $query->where('branch_id', $branchId); }
        if ($active === 'active') { $query->where('is_active', true)->whereNull('deleted_at'); }
        elseif ($active === 'inactive') { $query->where(fn ($x) => $x->where('is_active', false)->orWhereNotNull('deleted_at')); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('name'),
                ['ID', 'Name', 'Specialty', 'Phone', 'Email', 'License #', 'Branch', 'Active'],
                fn ($d) => [$d->id, $d->name, $d->specialty, $d->phone, $d->email, $d->license_number, $d->branch?->localized_name, $d->is_active ? 'Yes' : 'No'],
                'Doctors',
                app()->getLocale() === 'ar',
            ),
            'doctors-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            'active' => $request->input('active', 'all'),
        ];

        $query = Doctor::query()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])
            ->with(['branch:id,name', 'partner:id,name', 'user:id,name,email']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('license_number', 'like', "%{$q}%")
                    ->orWhere('specialty', 'like', "%{$q}%");
            });
        }
        if ($filters['branch_id'] !== null) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if ($filters['active'] === 'active') {
            $query->where('is_active', true)->whereNull('deleted_at');
        } elseif ($filters['active'] === 'inactive') {
            $query->where(function ($q) {
                $q->where('is_active', false)->orWhereNotNull('deleted_at');
            });
        }

        $page = $query->orderBy('name')->paginate(25)->withQueryString();

        // Branch.name is a translatable field; on serialization it expands to the
        // full {en,ar} array, which leaks into the table column as raw JSON. Collapse
        // the eager-loaded relation to the localized string (Fluent is Arrayable so
        // `row.branch.name` keeps working on the frontend).
        $svc = app(WorkingHoursService::class);

        $page->getCollection()->each(function ($d) use ($svc) {
            if ($d->relationLoaded('branch') && $d->branch) {
                $d->setRelation('branch', new \Illuminate\Support\Fluent([
                    'id' => $d->branch->id,
                    'name' => $d->branch->localized_name,
                ]));
            }
            // The edit form needs all seven rows, not just the worked days.
            $d->setAttribute('hours', $svc->doctorHoursPayload($d, $d->branch_id));
            $d->setAttribute('hours_summary', $svc->normalizeDoctorHours((array) ($d->working_hours ?? [])));
        });

        $branches = $this->accessibleBranches()->all();

        // Per-branch weekly windows, so the form can grey out closed days and
        // clamp the time inputs before the user ever hits Save.
        $branchWindows = [];
        $branchSlotLengths = [];
        foreach ($branches as $b) {
            $branchWindows[(int) $b['id']] = $svc->branchWindowsForForm((int) $b['id']);
            // Shown as the placeholder on the doctor's appointment-length field,
            // so "empty" reads as a value rather than as nothing.
            $branchSlotLengths[(int) $b['id']] = $svc->branchHoursPayload((int) $b['id'])['settings']['slot_length_minutes'];
        }

        // Partner (clinic) options scoped to the user's clinics — global admin
        // sees all. The form cascades Partner → Branch off this.
        $partnerIds = $this->accessiblePartnerIds();
        $partners = Partner::query()
            ->when($partnerIds !== null, fn ($q) => $q->whereIn('id', $partnerIds ?: [0]))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all();

        // Rooms for the branch→room cascade. Branch-scoped automatically; each
        // carries its currently-assigned doctor (if any) so the form can show
        // only free rooms (plus the doctor's own room when editing).
        $rooms = \App\Models\RestaurantTable::query()
            ->with('doctor:id,restaurant_table_id')
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'branch_id' => $r->branch_id, 'doctor_id' => $r->doctor?->id])
            ->all();

        $counts = [
            'total' => Doctor::query()
                ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])->count(),
            'active' => Doctor::query()->where('is_active', true)->count(),
            'inactive' => Doctor::query()
                ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])
                ->where(function ($q) { $q->where('is_active', false)->orWhereNotNull('deleted_at'); })
                ->count(),
        ];

        return Inertia::render('Doctors/Index', [
            'filters' => $filters,
            'page' => $page,
            'branches' => $branches,
            'branch_windows' => $branchWindows,
            'branch_slot_lengths' => $branchSlotLengths,
            'partners' => $partners,
            'rooms' => $rooms,
            'counts' => $counts,
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403, 'Not authorized to create doctors.');
        }
        $data = $this->validated($request, null, true);

        // Mirror the old Filament CreateDoctor flow: the email IS the doctor's
        // login. Find-or-create the user, give it the clinic_doctor role, and
        // surface a generated password once. No "pick a user" dropdown.
        $generated = null;
        $doctor = DB::transaction(function () use ($data, &$generated) {
            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                $generated = Str::password(12, symbols: false);
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $generated,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            }
            if (! $user->hasRole('clinic_doctor')) {
                $user->assignRole('clinic_doctor');
            }
            $data['user_id'] = $user->id;

            return Doctor::create($data);
        });

        $msg = 'Doctor added.';
        if ($generated) {
            $msg = "Doctor added. Login: {$doctor->email} · temporary password: {$generated} (shown once — copy it now).";
        }

        return back()->with('flash', ['type' => 'success', 'message' => $msg]);
    }

    public function update(Request $request, Doctor $doctor): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403, 'Not authorized to update doctors.');
        }
        // Email is the user's login — locked after creation, like the old admin.
        $data = $this->validated($request, $doctor->id, false);
        unset($data['email']);
        $doctor->update($data);
        return back()->with('flash', ['type' => 'success', 'message' => 'Doctor updated.']);
    }

    public function destroy(Request $request, Doctor $doctor): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403, 'Not authorized to delete doctors.');
        }
        $doctor->delete();
        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Doctor archived.',
            'undo' => ['url' => route('v2.doctors.restore', ['doctor' => $doctor->id]), 'label' => 'Undo'],
        ]);
    }

    public function restore(Request $request, int $doctor): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }
        $d = Doctor::withTrashed()->findOrFail($doctor);
        $d->restore();
        return back()->with('flash', ['type' => 'success', 'message' => 'Doctor restored.']);
    }

    /** Archive a set of doctors (soft delete) with a single Undo. */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $ids = $this->validatedIds($request);
        if (empty($ids)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Nothing selected.']);
        }
        Doctor::whereIn('id', $ids)->get()->each->delete();

        return back()->with('flash', [
            'type' => 'success',
            'message' => count($ids).' doctor(s) archived.',
            'undo' => ['url' => route('v2.doctors.bulk-restore', ['ids' => $ids]), 'label' => 'Undo'],
        ]);
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $ids = $this->validatedIds($request);
        if (! empty($ids)) {
            Doctor::withTrashed()->whereIn('id', $ids)->get()->each->restore();
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Restored.']);
    }

    protected function validatedIds(Request $request): array
    {
        $data = $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['integer']]);
        return array_values(array_unique(array_map('intval', $data['ids'] ?? [])));
    }

    protected function validated(Request $request, ?int $exceptId = null, bool $isCreate = false): array
    {
        // Partner + branch are required (and the branch must belong to the
        // partner) so a doctor can never be assigned across clinics — matching
        // the old admin's Partner→Branch cascade. user_id is derived from the
        // email, never accepted from the client.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Required + NOT NULL in the DB (old admin marks it required too).
            'specialty' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => $isCreate
                ? ['required', 'email', 'max:191', Rule::unique('users', 'email')]
                : ['nullable', 'email', 'max:191'],
            'license_number' => ['nullable', 'string', 'max:64', Rule::unique('doctors', 'license_number')->ignore($exceptId)->whereNull('deleted_at')],
            // Consultation fee is mandatory and must be > 0 (matches old admin).
            'consultation_fee' => ['required', 'numeric', 'gt:0'],
            // How long one appointment with this doctor takes. Empty = inherit
            // the branch's appointment length.
            'default_slot_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'partner_id' => ['required', 'integer', Rule::exists('partners', 'id')->where(fn ($q) => $this->scopePartnerRule($q))],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('partner_id', (int) $request->input('partner_id'))],
            // Room: optional, must belong to the chosen branch, and not already
            // taken by another doctor (one doctor ↔ one room).
            'restaurant_table_id' => ['nullable', 'integer',
                Rule::exists('restaurant_tables', 'id')->where('branch_id', (int) $request->input('branch_id')),
                Rule::unique('doctors', 'restaurant_table_id')->ignore($exceptId)->whereNull('deleted_at'),
            ],
            'bio' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            // Weekly schedule. All seven days are submitted; `is_open` marks the
            // ones the doctor actually works.
            'working_hours' => ['sometimes', 'array', 'max:7'],
            'working_hours.*.day' => ['required', 'integer', 'between:0,6'],
            'working_hours.*.is_open' => ['required', 'boolean'],
            'working_hours.*.start' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i'],
            'working_hours.*.end' => ['required_if:working_hours.*.is_open,true', 'nullable', 'date_format:H:i'],
        ]);
        $data['is_active'] = (bool) $request->input('is_active', true);
        $data['default_slot_minutes'] = $data['default_slot_minutes'] ?? null;

        if ($request->has('working_hours')) {
            $data['working_hours'] = $this->validatedWorkingHours(
                (array) $request->input('working_hours', []),
                (int) $data['branch_id'],
            );
        }

        return $data;
    }

    /**
     * Normalize the submitted schedule and enforce the core rule: a doctor
     * can't be scheduled outside their branch's opening hours, or at all on a
     * day the branch is shut.
     */
    protected function validatedWorkingHours(array $submitted, int $branchId): array
    {
        $svc = app(WorkingHoursService::class);
        $hours = $svc->normalizeDoctorHours($submitted);

        // A day switched on but with end <= start is dropped by normalize(),
        // which would silently swallow the mistake — call it out instead.
        $errors = [];
        foreach ($submitted as $i => $row) {
            if (! is_array($row) || empty($row['is_open'])) {
                continue;
            }
            $start = $svc->toMinutes($row['start'] ?? null);
            $end = $svc->toMinutes($row['end'] ?? null);
            if ($start !== null && $end !== null && $end <= $start) {
                $day = $svc->dayName((int) ($row['day'] ?? 0));
                $errors["working_hours.{$i}.end"] = "{$day}: the end time must be after the start time.";
            }
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        // Branch-window violations are reported against the row index the form
        // submitted, so the error lands on the right day.
        $violations = $svc->validateDoctorHours($hours, $branchId);
        if ($violations) {
            $byIndex = [];
            foreach ($violations as $key => $message) {
                $dow = (int) substr($key, strrpos($key, '.') + 1);
                $i = collect($submitted)->search(fn ($r) => is_array($r) && (int) ($r['day'] ?? -1) === $dow);
                $byIndex[$i === false ? "working_hours.{$dow}" : "working_hours.{$i}.start"] = $message;
            }
            throw \Illuminate\Validation\ValidationException::withMessages($byIndex);
        }

        return $hours;
    }

    /** Restrict the partner_id rule to the user's clinics (global admin = any). */
    protected function scopePartnerRule($q)
    {
        $ids = $this->accessiblePartnerIds();
        if ($ids !== null) {
            $q->whereIn('id', $ids ?: [0]);
        }

        return $q;
    }
}
