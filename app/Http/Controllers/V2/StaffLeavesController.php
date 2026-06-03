<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\StaffLeave;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff leaves — v2 replacement for Filament StaffLeaveResource.
 *
 * Access model mirrors the Filament resource:
 *   - anyone with view_any_staff_leaves can open the page
 *   - HR managers (delete_any_staff_leaves) see everyone + can approve/reject
 *   - non-managers see only their own; can request + cancel pending; cannot
 *     touch other people's rows
 */
class StaffLeavesController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_staff_leaves')) {
            abort(403, 'Not authorized to view staff leaves.');
        }
    }

    protected function isHrManager(Request $request): bool
    {
        return (bool) $request->user()?->can('delete_any_staff_leaves');
    }

    /** Styled .xlsx export of leave requests (mirrors filters + self/HR gating). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $hrManager = $this->isHrManager($request);
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');
        $type = $request->input('type', 'all');
        $userId = $hrManager ? ($request->input('user_id', '') !== '' ? (int) $request->input('user_id') : null) : (int) $request->user()->id;

        $query = StaffLeave::query()->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])->with(['user:id,name,email']);
        if (! $hrManager) { $query->where('user_id', $request->user()->id); }
        elseif ($userId !== null) { $query->where('user_id', $userId); }
        if ($q !== '') { $query->whereHas('user', fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")); }
        if ($status !== 'all') { $query->where('status', $status); }
        if ($type !== 'all') { $query->where('type', $type); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'Staff', 'Type', 'Start', 'End', 'Days', 'Status', 'Reason'],
                fn ($l) => [$l->id, $l->user?->name, $l->type, (string) $l->starts_on, (string) $l->ends_on, $l->days_count, $l->status, $l->reason],
                'Leave Requests',
                app()->getLocale() === 'ar',
            ),
            'staff-leaves-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $hrManager = $this->isHrManager($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', 'all'),
            'type' => $request->input('type', 'all'),
            'user_id' => $hrManager
                ? ($request->input('user_id', '') !== '' ? (int) $request->input('user_id') : null)
                : (int) $request->user()->id,
        ];

        $query = StaffLeave::query()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])
            ->with(['user:id,name,email', 'doctor:id,name', 'decidedBy:id,name']);

        // Non-managers can only see their own.
        if (! $hrManager) {
            $query->where('user_id', $request->user()->id);
        } elseif ($filters['user_id'] !== null) {
            $query->where('user_id', $filters['user_id']);
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('user', fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if ($filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();

        $staffOptions = $hrManager
            ? User::query()->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->all()
            : [];

        $counts = [
            'total' => (clone $query)->count(),
            'pending' => StaffLeave::query()
                ->when(! $hrManager, fn ($q) => $q->where('user_id', $request->user()->id))
                ->where('status', StaffLeave::STATUS_PENDING)->count(),
            'approved' => StaffLeave::query()
                ->when(! $hrManager, fn ($q) => $q->where('user_id', $request->user()->id))
                ->where('status', StaffLeave::STATUS_APPROVED)->count(),
        ];

        return Inertia::render('StaffLeaves/Index', [
            'filters' => $filters,
            'page' => $page,
            'staff_options' => $staffOptions,
            'counts' => $counts,
            'is_hr_manager' => $hrManager,
            'current_user_id' => (int) $request->user()->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $hrManager = $this->isHrManager($request);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', 'in:annual,sick,maternity,unpaid,emergency,other'],
            // Regular staff can't request leave for a past date; HR managers may
            // still backfill historical leave for someone.
            'starts_on' => array_filter(['required', 'date', $hrManager ? null : 'after_or_equal:today']),
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Non-managers can only request for themselves.
        if (! $hrManager && (int) $data['user_id'] !== (int) $request->user()->id) {
            abort(403, 'You can only request leave for yourself.');
        }

        $data['requested_by_user_id'] = $request->user()->id;

        StaffLeave::create($data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Leave request submitted.']);
    }

    public function update(Request $request, StaffLeave $staffLeave): RedirectResponse
    {
        $this->authorizeAccess($request);
        $hrManager = $this->isHrManager($request);

        $ownsRow = $staffLeave->user_id === $request->user()->id;
        if (! $hrManager && ! $ownsRow) {
            abort(403, "Can't edit someone else's leave.");
        }
        if (! $hrManager && $staffLeave->status !== StaffLeave::STATUS_PENDING) {
            abort(403, "Can't edit a leave that's already been decided.");
        }

        $data = $request->validate([
            'type' => ['required', 'string', 'in:annual,sick,maternity,unpaid,emergency,other'],
            // Same past-date floor as store(): regular staff can't move a request
            // into the past via edit; HR managers may backfill historical leave.
            'starts_on' => array_filter(['required', 'date', $hrManager ? null : 'after_or_equal:today']),
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $staffLeave->update($data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Leave updated.']);
    }

    public function decide(Request $request, StaffLeave $staffLeave): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->isHrManager($request)) {
            abort(403, 'Only HR managers can approve/reject leaves.');
        }
        if ($staffLeave->status !== StaffLeave::STATUS_PENDING) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Leave is no longer pending.']);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $staffLeave->forceFill([
            'status' => $data['status'],
            'decision_notes' => $data['decision_notes'] ?? null,
            'decided_at' => now(),
            'decided_by_user_id' => $request->user()->id,
        ])->save();

        return back()->with('flash', ['type' => 'success', 'message' => "Leave {$data['status']}."]);
    }

    public function destroy(Request $request, StaffLeave $staffLeave): RedirectResponse
    {
        $this->authorizeAccess($request);
        $hrManager = $this->isHrManager($request);

        $ownsPending = $staffLeave->user_id === $request->user()->id
            && $staffLeave->status === StaffLeave::STATUS_PENDING;

        if (! $hrManager && ! $ownsPending) {
            abort(403, "Can't delete this leave.");
        }

        $staffLeave->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Leave removed.']);
    }
}
