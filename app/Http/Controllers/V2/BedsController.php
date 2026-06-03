<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Inpatient\Bed;
use App\Models\Inpatient\Ward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BedsController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_beds')) {
            abort(403);
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_beds');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'ward_id' => $request->input('ward_id', '') !== '' ? (int) $request->input('ward_id') : null,
            'status' => $request->input('status', 'all'),
        ];

        $query = Bed::query()->with('ward:id,name,code,branch_id');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where('code', 'like', "%{$q}%");
        }
        if ($filters['ward_id']) {
            $query->where('ward_id', $filters['ward_id']);
        }
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderBy('code')->paginate(50)->withQueryString();

        $wards = Ward::query()->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'code' => $w->code])->all();

        $counts = [
            'total' => Bed::query()->count(),
            'available' => Bed::query()->where('status', 'available')->count(),
            'occupied' => Bed::query()->where('status', 'occupied')->count(),
            'maintenance' => Bed::query()->whereIn('status', ['maintenance', 'cleaning'])->count(),
        ];

        return Inertia::render('Beds/Index', [
            'filters' => $filters,
            'page' => $page,
            'wards' => $wards,
            'statuses' => ['available', 'occupied', 'reserved', 'maintenance', 'cleaning'],
            'counts' => $counts,
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $data = $this->validated($request);
        // Inherit branch_id from ward.
        $ward = Ward::findOrFail($data['ward_id']);
        $data['branch_id'] = $ward->branch_id;
        Bed::create($data);
        return back()->with('flash', ['type' => 'success', 'message' => 'Bed added.']);
    }

    public function update(Request $request, Bed $bed): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $bed->update($this->validated($request, $bed->id));
        return back()->with('flash', ['type' => 'success', 'message' => 'Bed updated.']);
    }

    public function destroy(Request $request, Bed $bed): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        if ($bed->status === 'occupied') {
            return back()->with('flash', ['type' => 'error', 'message' => "Can't delete an occupied bed."]);
        }
        $bed->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Bed removed.']);
    }

    protected function validated(Request $request, ?int $exceptId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('beds', 'code')->where(fn ($q) => $q->where('ward_id', $request->input('ward_id')))->ignore($exceptId)],
            'ward_id' => ['required', 'integer', 'exists:wards,id'],
            'status' => ['required', 'string', 'in:available,occupied,reserved,maintenance,cleaning'],
            'daily_rate_override' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => (bool) $request->input('is_active', true),
        ];
    }
}
