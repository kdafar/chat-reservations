<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consultation rooms.
 *
 * Backed by `restaurant_tables` — the name is inherited from the restaurant
 * codebase this app was forked from; in the clinic a "table" is a consultation
 * room. The model keeps the legacy name so existing bookings/doctor links stay
 * intact.
 *
 * This screen matters more than it looks: BookingsController::doctorOptions()
 * only offers doctors that have a `restaurant_table_id`, because a booking
 * reserves a doctor AND a room together. A branch with no rooms therefore has
 * no bookable doctors at all. Rooms management previously existed only in the
 * retired Filament admin, which left no way to fix that from the UI.
 */
class RoomsController extends Controller
{
    use ResolvesAccessibleClinics;

    /** Room states the booking + check-in flows understand. */
    public const STATUSES = ['available', 'occupied', 'out_of_service'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_restaurant_table')) {
            abort(403);
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_restaurant_table');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'branch_id' => $request->input('branch_id', '') !== '' ? (int) $request->input('branch_id') : null,
            'status' => $request->input('status', ''),
        ];

        $query = RestaurantTable::query()
            ->with(['branch:id,name', 'doctor:id,name,restaurant_table_id']);

        if ($filters['q'] !== '') {
            $query->where('name', 'like', '%'.$filters['q'].'%');
        }
        if ($filters['branch_id']) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderBy('name')->paginate(25)->withQueryString();

        // Branch.name is translatable; flatten it to the active locale so the
        // table column can print it directly (same approach as Wards).
        $page->getCollection()->each(function (RestaurantTable $r) {
            if ($r->relationLoaded('branch') && $r->branch) {
                $r->setRelation('branch', new \Illuminate\Support\Fluent([
                    'id' => $r->branch->id,
                    'name' => $r->branch->localized_name,
                ]));
            }
        });

        $counts = [
            'total' => RestaurantTable::query()->count(),
            'available' => RestaurantTable::query()->where('status', 'available')->count(),
            // Rooms nobody can be booked into yet — the actionable number.
            'unassigned' => RestaurantTable::query()->whereDoesntHave('doctor')->count(),
        ];

        return Inertia::render('Rooms/Index', [
            'filters' => $filters,
            'page' => $page,
            'branches' => $this->accessibleBranches()->all(),
            'statuses' => self::STATUSES,
            'counts' => $counts,
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }

        RestaurantTable::create($this->validated($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Room added.']);
    }

    public function update(Request $request, RestaurantTable $room): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }

        $room->update($this->validated($request, $room->id));

        return back()->with('flash', ['type' => 'success', 'message' => 'Room updated.']);
    }

    public function destroy(Request $request, RestaurantTable $room): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) {
            abort(403);
        }

        // Deleting a room out from under a doctor would silently make that
        // doctor unbookable, which is exactly the failure this screen exists to
        // prevent. Make the caller unassign first.
        if ($room->doctor()->exists()) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => "Can't delete a room that a doctor is assigned to. Move the doctor to another room first.",
            ]);
        }

        if ($room->bookings()->exists()) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => "Can't delete a room that has bookings against it.",
            ]);
        }

        $room->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Room removed.']);
    }

    protected function validated(Request $request, ?int $exceptId = null): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:191',
                // Room names are how staff tell them apart on the booking sheet,
                // so keep them unique per branch.
                Rule::unique('restaurant_tables', 'name')
                    ->where(fn ($q) => $q->where('branch_id', $request->input('branch_id')))
                    ->ignore($exceptId),
            ],
            'branch_id' => [
                'required', 'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $this->scopeBranchRule($q)),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
        ]);

        $data['capacity'] = $data['capacity'] ?? 1;

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
