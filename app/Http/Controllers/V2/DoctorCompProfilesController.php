<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorCompensationProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Doctor Compensation Profiles — v2 replacement for Filament DoctorCompensationProfileResource.
 * Editable per-doctor rate config. type=percentage needs percentage_rate. Branch+doctor scoped.
 */
class DoctorCompProfilesController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_doctor_compensation_profiles')) {
            abort(403, 'Not authorized to view compensation profiles.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_doctor_compensation_profiles');
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $type = $request->input('type', 'all');
        $query = DoctorCompensationProfile::query()->with('doctor:id,name');
        if ($q !== '') { $query->whereHas('doctor', fn ($d) => $d->where('name', 'like', "%{$q}%")); }
        if (in_array($type, ['salary', 'percentage'], true)) { $query->where('type', $type); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'Doctor', 'Type', 'Basis', 'Percentage rate', 'Active'],
                fn ($p) => [$p->id, $p->doctor?->name ?? ('#'.$p->doctor_id), $p->type, $p->basis, $p->percentage_rate, $p->is_active ? 'Yes' : 'No'],
                'Doctor Compensation Profiles',
                app()->getLocale() === 'ar',
            ),
            'doctor-compensation-profiles-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'type' => $request->input('type', 'all'),
        ];

        $query = DoctorCompensationProfile::query()->with('doctor:id,name');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('doctor', fn ($d) => $d->where('name', 'like', "%{$q}%"));
        }
        if (in_array($filters['type'], ['salary', 'percentage'], true)) {
            $query->where('type', $filters['type']);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(function (DoctorCompensationProfile $p) {
            $p->setAttribute('doctor_name', $p->doctor?->name ?? ('#'.$p->doctor_id));
            return $p;
        });

        return Inertia::render('DoctorCompProfiles/Index', [
            'filters' => $filters,
            'page' => $page,
            'doctors' => Doctor::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name ?? ('#'.$d->id)])->all(),
            'types' => ['salary', 'percentage'],
            'bases' => ['fees_only', 'net_profit'],
            'counts' => [
                'total' => DoctorCompensationProfile::query()->count(),
                'active' => DoctorCompensationProfile::query()->where('is_active', true)->count(),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        DoctorCompensationProfile::create($this->validated($request));
        return back()->with('flash', ['type' => 'success', 'message' => 'Profile added.']);
    }

    public function update(Request $request, DoctorCompensationProfile $profile): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        // doctor_id is immutable after creation (matches Filament).
        $data = $this->validated($request);
        unset($data['doctor_id']);
        $profile->update($data);
        return back()->with('flash', ['type' => 'success', 'message' => 'Profile updated.']);
    }

    public function destroy(Request $request, DoctorCompensationProfile $profile): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('delete_doctor_compensation_profiles')) abort(403);
        $profile->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Profile deleted.']);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'doctor_id' => ['required', 'integer', Rule::exists('doctors', 'id')],
            'type' => ['required', Rule::in(['salary', 'percentage'])],
            'basis' => ['required', Rule::in(['fees_only', 'net_profit'])],
            'percentage_rate' => ['nullable', 'required_if:type,percentage', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'doctor_id' => $data['doctor_id'],
            'type' => $data['type'],
            'basis' => $data['basis'],
            'percentage_rate' => $data['type'] === 'percentage' ? $data['percentage_rate'] : null,
            'is_active' => (bool) $request->input('is_active', true),
        ];
    }
}
