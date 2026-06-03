<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Inpatient\Ward;
use App\Models\Partner;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WardsController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_wards')) {
            abort(403);
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_wards');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            'ward_type' => $request->input('ward_type', ''),
        ];

        $query = Ward::query()
            ->with('branch:id,name')
            ->withCount('beds');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%");
            });
        }
        if ($filters['branch_id']) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if ($filters['ward_type']) {
            $query->where('ward_type', $filters['ward_type']);
        }

        $page = $query->orderBy('name')->paginate(25)->withQueryString();

        // Collapse translatable Branch.name relation to the localized string for the table column.
        $page->getCollection()->each(function ($w) {
            if ($w->relationLoaded('branch') && $w->branch) {
                $w->setRelation('branch', new \Illuminate\Support\Fluent([
                    'id' => $w->branch->id,
                    'name' => $w->branch->localized_name,
                ]));
            }
        });

        $branches = $this->accessibleBranches()->all();
        $partners = Partner::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all();

        $counts = [
            'total' => Ward::query()->count(),
            'active' => Ward::query()->where('is_active', true)->count(),
        ];

        return Inertia::render('Wards/Index', [
            'filters' => $filters,
            'page' => $page,
            'branches' => $branches,
            'partners' => $partners,
            'ward_types' => ['general', 'icu', 'pediatric', 'maternity', 'vip', 'isolation'],
            'counts' => $counts,
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        Ward::create($this->validated($request));
        return back()->with('flash', ['type' => 'success', 'message' => 'Ward added.']);
    }

    public function update(Request $request, Ward $ward): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        $ward->update($this->validated($request, $ward->id));
        return back()->with('flash', ['type' => 'success', 'message' => 'Ward updated.']);
    }

    public function destroy(Request $request, Ward $ward): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);
        if ($ward->beds()->count() > 0) {
            return back()->with('flash', ['type' => 'error', 'message' => "Can't delete a ward with beds. Remove beds first."]);
        }
        $ward->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Ward removed.']);
    }

    protected function validated(Request $request, ?int $exceptId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:32', Rule::unique('wards', 'code')->where(fn ($q) => $q->where('branch_id', $request->input('branch_id')))->ignore($exceptId)],
            'ward_type' => ['required', 'string', 'in:general,icu,pediatric,maternity,vip,isolation'],
            // Branch is restricted to the user's clinic; partner_id is NOT taken
            // from the client — it's inherited from the branch (matches the old
            // admin, where the partner field is hidden + auto-derived on save).
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where(fn ($q) => $this->scopeBranchRule($q))],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'gender_restriction' => ['nullable', 'string', 'in:any,male,female'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['partner_id'] = \App\Models\Branch::query()->whereKey($data['branch_id'])->value('partner_id');
        $data['is_active'] = (bool) $request->input('is_active', true);

        return $data;
    }

    /** Limit the branch_id rule to the user's clinic (global admin = any). */
    protected function scopeBranchRule($q)
    {
        $ids = $this->accessibleBranchIds();
        if ($ids !== null) {
            $q->whereIn('id', $ids ?: [0]);
        }

        return $q;
    }
}
