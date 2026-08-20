<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\BranchBlackout;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branch closure days — Eid, National Day, maintenance, anything that shuts a
 * branch for a whole date regardless of its normal opening hours.
 *
 * AvailabilityService and WorkingHoursService both read these to drop a day
 * from the bookable calendar. Nothing outside the retired Filament admin could
 * ever write one, so closures could not be entered at all and bookings were
 * accepted on days the clinic was shut. This is the v2 replacement.
 *
 * Regular weekly hours are NOT set here — those live on the branch itself
 * (Branches → edit → hours). A blackout is the one-off exception.
 */
class BranchBlackoutsController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_branch_blackout')) {
            abort(403);
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_branch_blackout');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            // Past closures are history; default the list to what still matters.
            'upcoming' => $request->input('upcoming', '1') === '1',
        ];

        $query = BranchBlackout::query()->with('branch:id,name');

        if ($filters['q'] !== '') {
            $query->where('reason', 'like', '%'.$filters['q'].'%');
        }
        if ($filters['branch_id']) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if ($filters['upcoming']) {
            $query->whereDate('date', '>=', now()->toDateString());
        }

        $page = $query->orderBy('date')->paginate(25)->withQueryString();

        $page->getCollection()->each(function (BranchBlackout $b) {
            if ($b->relationLoaded('branch') && $b->branch) {
                $b->setRelation('branch', new \Illuminate\Support\Fluent([
                    'id' => $b->branch->id,
                    'name' => $b->branch->localized_name,
                ]));
            }
        });

        return Inertia::render('BranchBlackouts/Index', [
            'filters' => $filters,
            'page' => $page,
            'branches' => $this->accessibleBranches()->all(),
            'counts' => [
                'total' => BranchBlackout::query()->count(),
                'upcoming' => BranchBlackout::query()->whereDate('date', '>=', now()->toDateString())->count(),
            ],
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }

        BranchBlackout::create($this->validated($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Closure day added.']);
    }

    public function update(Request $request, BranchBlackout $blackout): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }

        $blackout->update($this->validated($request, $blackout->id));

        return back()->with('flash', ['type' => 'success', 'message' => 'Closure day updated.']);
    }

    public function destroy(Request $request, BranchBlackout $blackout): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }

        $blackout->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Closure day removed — the branch is bookable again that day.']);
    }

    protected function validated(Request $request, ?int $exceptId = null): array
    {
        return $request->validate([
            'branch_id' => [
                'required', 'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $this->scopeBranchRule($q)),
            ],
            // One closure per branch per date — a second row would be a no-op
            // that only confuses the list.
            'date' => [
                'required', 'date_format:Y-m-d',
                Rule::unique('branch_blackouts', 'date')
                    ->where(fn ($q) => $q->where('branch_id', $request->input('branch_id')))
                    ->ignore($exceptId),
            ],
            'reason' => ['nullable', 'string', 'max:191'],
        ], [
            'date.unique' => 'That branch already has a closure on this date.',
        ]);
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
