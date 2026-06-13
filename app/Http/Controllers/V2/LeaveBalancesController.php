<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\StaffCompensationProfile;
use App\Models\StaffLeaveEntitlement;
use App\Models\User;
use App\Services\Clinic\LeaveBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Annual leave balances. Shows entitled + carried-over − used (approved) =
 * remaining, per staff member per year. HR sets the entitlement; "used" is
 * always computed live from approved staff_leaves by LeaveBalanceService.
 */
class LeaveBalancesController extends Controller
{
    public function __construct(protected LeaveBalanceService $leave) {}

    protected function authorizeAccess(Request $request): void
    {
        // Reuse the leave-entitlements permission; falls back to staff_leaves HR.
        $u = $request->user();
        if (! $u || (! $u->can('view_any_staff_leave_entitlements') && ! $u->can('delete_any_staff_leaves'))) {
            abort(403, 'Not authorized to view leave balances.');
        }
    }

    protected function canManage(Request $request): bool
    {
        $u = $request->user();
        return (bool) ($u?->can('update_staff_leave_entitlements') || $u?->can('delete_any_staff_leaves'));
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $year = (int) $request->input('year', now()->year);
        $q = trim((string) $request->input('q', ''));

        // Build the roster: everyone with a salary profile (active or not) plus
        // anyone who already has an entitlement row for the year.
        $profileUserIds = StaffCompensationProfile::query()->pluck('user_id')->all();
        $entUserIds = StaffLeaveEntitlement::where('year', $year)->pluck('user_id')->all();
        $userIds = array_values(array_unique(array_merge($profileUserIds, $entUserIds)));

        $usersQuery = User::query()->whereIn('id', $userIds);
        if ($q !== '') {
            $usersQuery->where(fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }
        $users = $usersQuery->orderBy('name')->get(['id', 'name', 'email']);

        $profiles = StaffCompensationProfile::query()->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $entitlements = StaffLeaveEntitlement::where('year', $year)->where('leave_type', 'annual')
            ->whereIn('user_id', $userIds)->get()->keyBy('user_id');

        $rows = $users->map(function ($u) use ($year, $entitlements, $profiles) {
            $ent = $entitlements->get($u->id);
            $default = (int) ($profiles->get($u->id)->annual_leave_days ?? 0);
            $entitled = $ent ? (float) $ent->entitled_days : (float) $default;
            $carried = $ent ? (float) $ent->carried_over_days : 0.0;
            $used = $this->leave->usedDays($u->id, $year, 'annual');
            $pending = $this->leave->pendingDays($u->id, $year, 'annual');

            return [
                'user_id' => $u->id,
                'user_name' => $u->name,
                'user_email' => $u->email,
                'entitled_days' => $entitled,
                'carried_over_days' => $carried,
                'used_days' => $used,
                'pending_days' => $pending,
                'remaining_days' => round($entitled + $carried - $used, 2),
                'has_entitlement' => (bool) $ent,
                'default_entitlement' => $default,
            ];
        })->values();

        return Inertia::render('Payroll/LeaveBalances/Index', [
            'filters' => ['year' => $year, 'q' => $q],
            'rows' => $rows,
            'years' => range(now()->year + 1, now()->year - 4),
            'counts' => [
                'staff' => $rows->count(),
                'unset' => $rows->where('has_entitlement', false)->count(),
            ],
            'can_manage' => $this->canManage($request),
        ]);
    }

    /** Upsert one user's entitlement for a year. */
    public function upsert(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'entitled_days' => ['required', 'numeric', 'min:0', 'max:90'],
            'carried_over_days' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        StaffLeaveEntitlement::updateOrCreate(
            ['user_id' => $data['user_id'], 'year' => $data['year'], 'leave_type' => 'annual'],
            [
                'entitled_days' => round((float) $data['entitled_days'], 2),
                'carried_over_days' => round((float) ($data['carried_over_days'] ?? 0), 2),
                'notes' => $data['notes'] ?? null,
            ],
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Leave entitlement saved.']);
    }

    /** Seed entitlements for every salary-profiled staff member from their profile default. */
    public function seedYear(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        $year = (int) $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100']])['year'];

        $created = 0;
        foreach (StaffCompensationProfile::query()->where('is_active', true)->get() as $p) {
            $ent = StaffLeaveEntitlement::firstOrNew(['user_id' => $p->user_id, 'year' => $year, 'leave_type' => 'annual']);
            if (! $ent->exists) {
                $ent->entitled_days = (int) $p->annual_leave_days;
                $ent->carried_over_days = 0;
                $ent->save();
                $created++;
            }
        }

        return back()->with('flash', ['type' => 'success', 'message' => "Seeded {$created} entitlement(s) for {$year}."]);
    }
}
