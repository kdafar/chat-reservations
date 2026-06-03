<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff attendance — v2 replacement for Filament StaffAttendanceResource.
 *
 * Same access model as Staff Leaves:
 *   - view_any_staff_attendances → access page
 *   - delete_any_staff_attendances → HR manager (see all, edit any)
 *   - regular staff: see only their own; can clock in/out + edit own rows
 */
class StaffAttendancesController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_staff_attendances')) {
            abort(403, 'Not authorized to view staff attendance.');
        }
    }

    protected function isHrManager(Request $request): bool
    {
        return (bool) $request->user()?->can('delete_any_staff_attendances');
    }

    /** Styled .xlsx export of attendance (mirrors filters + the same self/HR gating). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $hrManager = $this->isHrManager($request);
        $q = trim((string) $request->input('q', ''));
        $userId = $hrManager ? ($request->input('user_id', '') !== '' ? (int) $request->input('user_id') : null) : (int) $request->user()->id;
        $from = $request->input('from', '');
        $to = $request->input('to', '');

        $query = StaffAttendance::query()->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])->with(['user:id,name,email']);
        if (! $hrManager) { $query->where('user_id', $request->user()->id); }
        elseif ($userId !== null) { $query->where('user_id', $userId); }
        if ($q !== '') { $query->whereHas('user', fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")); }
        if ($from) { $query->whereDate('work_date', '>=', $from); }
        if ($to) { $query->whereDate('work_date', '<=', $to); }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('work_date')->orderByDesc('id'),
                ['ID', 'Date', 'Staff', 'Clock in', 'Clock out', 'Hours', 'Notes'],
                fn ($a) => [$a->id, (string) $a->work_date, $a->user?->name, (string) $a->clock_in_at, (string) $a->clock_out_at, $a->hours_worked, $a->notes],
                'Attendance',
                app()->getLocale() === 'ar',
            ),
            'staff-attendances-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $hrManager = $this->isHrManager($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'user_id' => $hrManager
                ? ($request->input('user_id', '') !== '' ? (int) $request->input('user_id') : null)
                : (int) $request->user()->id,
            'from' => $request->input('from', ''),
            'to' => $request->input('to', ''),
        ];

        $query = StaffAttendance::query()
            ->withoutGlobalScopes([\Illuminate\Database\Eloquent\SoftDeletingScope::class])
            ->with(['user:id,name,email', 'doctor:id,name', 'recordedBy:id,name']);

        if (! $hrManager) {
            $query->where('user_id', $request->user()->id);
        } elseif ($filters['user_id'] !== null) {
            $query->where('user_id', $filters['user_id']);
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->whereHas('user', fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }
        if ($filters['from']) {
            $query->whereDate('work_date', '>=', $filters['from']);
        }
        if ($filters['to']) {
            $query->whereDate('work_date', '<=', $filters['to']);
        }

        $page = $query->orderByDesc('work_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $staffOptions = $hrManager
            ? User::query()->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all()
            : [];

        // Today's row for the current user — drives the "Clock me in/out" widget.
        $todayRow = StaffAttendance::query()
            ->where('user_id', $request->user()->id)
            ->where('work_date', now()->toDateString())
            ->first();

        $counts = [
            'me_this_week' => StaffAttendance::query()
                ->where('user_id', $request->user()->id)
                ->whereDate('work_date', '>=', now()->startOfWeek())
                ->sum('hours_worked'),
            'me_this_month' => StaffAttendance::query()
                ->where('user_id', $request->user()->id)
                ->whereDate('work_date', '>=', now()->startOfMonth())
                ->sum('hours_worked'),
        ];

        return Inertia::render('StaffAttendances/Index', [
            'filters' => $filters,
            'page' => $page,
            'staff_options' => $staffOptions,
            'today_row' => $todayRow,
            'counts' => $counts,
            'is_hr_manager' => $hrManager,
            'current_user_id' => (int) $request->user()->id,
        ]);
    }

    public function clockInSelf(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $userId = (int) $request->user()->id;
        $today = now()->toDateString();

        // Pre-check including trashed rows so we surface the friendly
        // "restore it" message instead of letting the unique index throw.
        $existing = StaffAttendance::withTrashed()
            ->where('user_id', $userId)
            ->where('work_date', $today)
            ->first();
        if ($existing) {
            $msg = $existing->trashed()
                ? "Today's attendance row is in trash — restore it instead."
                : 'Already clocked in today.';
            return back()->with('flash', ['type' => 'warning', 'message' => $msg]);
        }

        try {
            StaffAttendance::create([
                'user_id' => $userId,
                'work_date' => $today,
                'clock_in_at' => now(),
                'recorded_by_user_id' => $userId,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'staff_attendance_user_date_unique')
                || str_contains($e->getMessage(), 'Duplicate entry')) {
                return back()->with('flash', ['type' => 'warning', 'message' => 'Already clocked in today.']);
            }
            throw $e;
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Clocked in.']);
    }

    public function clockOut(Request $request, StaffAttendance $staffAttendance): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->isHrManager($request) && $staffAttendance->user_id !== $request->user()->id) {
            abort(403, "Can't clock out someone else.");
        }

        $staffAttendance->clock_out_at = now();
        $staffAttendance->save();

        return back()->with('flash', ['type' => 'success', 'message' => "Clocked out. {$staffAttendance->hours_worked} hours."]);
    }

    public function update(Request $request, StaffAttendance $staffAttendance): RedirectResponse
    {
        $this->authorizeAccess($request);
        // Only HR managers may EDIT recorded times — staff can clock in/out
        // (deliberate, timestamped actions) but not alter their own records.
        abort_unless($this->isHrManager($request), 403, 'Only HR managers can edit attendance records.');

        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $staffAttendance->update($data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Attendance updated.']);
    }

    public function destroy(Request $request, StaffAttendance $staffAttendance): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->isHrManager($request)) {
            abort(403, 'Only HR managers can delete attendance rows.');
        }
        $staffAttendance->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Attendance removed.']);
    }
}
