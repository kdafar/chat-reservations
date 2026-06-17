<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\StaffCompensationProfile;
use App\Models\StaffLoan;
use App\Models\StaffSettlement;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Clinic\GratuityService;
use App\Services\Clinic\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * End-of-service settlements. Computes a Kuwait-law gratuity default (see
 * GratuityService) plus leave encashment, nets off outstanding staff loans,
 * then accrues (Dr 6016/6015 / Cr 2030 + loan clawback) and pays (Cr cash).
 */
class StaffSettlementsController extends Controller
{
    public function __construct(
        protected GratuityService $gratuity,
        protected LeaveBalanceService $leave,
        protected AccountingService $accounting,
    ) {}

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_staff_settlements')) {
            abort(403, 'Not authorized to view settlements.');
        }
    }

    protected function canManage(Request $request): bool
    {
        return (bool) $request->user()?->can('update_staff_settlements');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = ['status' => $request->input('status', 'all')];
        $query = StaffSettlement::query()->with(['user:id,name,email', 'branch:id,name']);
        if (in_array($filters['status'], ['draft', 'approved', 'paid'], true)) {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $page->getCollection()->transform(function (StaffSettlement $s) {
            $s->setAttribute('user_name', $s->user?->name ?? ('#'.$s->user_id));
            $s->setAttribute('branch_name', $s->branch?->name);

            return $s;
        });

        // Staff who could be settled — those with a salary profile, no paid settlement yet.
        $settled = StaffSettlement::where('status', 'paid')->pluck('user_id')->all();
        $candidates = StaffCompensationProfile::query()->with('user:id,name,email')
            ->whereNotIn('user_id', $settled)->get()
            ->map(fn ($p) => ['id' => $p->user_id, 'name' => $p->user?->name ?? ('#'.$p->user_id), 'email' => $p->user?->email])
            ->values()->all();

        return Inertia::render('Payroll/Settlements/Index', [
            'filters' => $filters,
            'page' => $page,
            'candidates' => $candidates,
            'payment_accounts' => $this->paymentAccountOptions(),
            'counts' => [
                'total' => StaffSettlement::count(),
                'draft' => StaffSettlement::where('status', 'draft')->count(),
                'paid_total' => round((float) StaffSettlement::where('status', 'paid')->sum('net_settlement'), 3),
            ],
            'can_manage' => $this->canManage($request),
        ]);
    }

    /**
     * Compute a settlement preview for a user/last-day/mode without saving.
     * Returns the gratuity default, leave encashment, outstanding loans, etc.
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'last_working_day' => ['required', 'date'],
            'mode' => ['required', 'in:termination,resignation'],
        ]);

        $profile = StaffCompensationProfile::where('user_id', $data['user_id'])->first();
        $basic = (float) ($profile->basic_salary ?? 0);
        $hireDate = $profile?->hire_date;

        $years = $hireDate ? $this->gratuity->yearsOfService($hireDate, $data['last_working_day']) : 0.0;
        $gratuity = $this->gratuity->gratuity($basic, $years, $data['mode']);

        // Leave encashment: remaining annual balance × daily wage (basic / 26).
        $year = (int) date('Y', strtotime($data['last_working_day']));
        $balance = $this->leave->balance((int) $data['user_id'], $year, 'annual');
        $remainingLeave = max(0, (float) $balance['remaining']);
        $leaveEncashment = $basic > 0 ? round(($basic / GratuityService::WORKING_DAYS_PER_MONTH) * $remainingLeave, 3) : 0.0;

        $outstandingLoans = round((float) StaffLoan::where('user_id', $data['user_id'])
            ->where('status', StaffLoan::STATUS_ACTIVE)->sum('outstanding_amount'), 3);

        return response()->json([
            'basic_salary' => $basic,
            'hire_date' => $hireDate ? $hireDate->toDateString() : null,
            'years_of_service' => $years,
            'gratuity_amount' => $gratuity,
            'remaining_leave_days' => $remainingLeave,
            'leave_encashment' => $leaveEncashment,
            'loan_clawback' => $outstandingLoans,
            'has_profile' => (bool) $profile,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        $data = $this->validated($request);

        $profile = StaffCompensationProfile::where('user_id', $data['user_id'])->first();
        $net = $this->net($data);

        StaffSettlement::create(array_merge($data, [
            'branch_id' => $profile?->branch_id,
            'hire_date' => $profile?->hire_date,
            'basic_salary_snapshot' => $data['basic_salary_snapshot'],
            'net_settlement' => $net,
            'status' => StaffSettlement::STATUS_DRAFT,
            'prepared_by_user_id' => $request->user()->id,
        ]));

        return back()->with('flash', ['type' => 'success', 'message' => 'Settlement drafted.']);
    }

    public function update(Request $request, StaffSettlement $staffSettlement): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        if ($staffSettlement->status !== StaffSettlement::STATUS_DRAFT) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only draft settlements can be edited.']);
        }
        $data = $this->validated($request);
        unset($data['user_id']);
        $staffSettlement->update(array_merge($data, ['net_settlement' => $this->net(array_merge($staffSettlement->toArray(), $data))]));

        return back()->with('flash', ['type' => 'success', 'message' => 'Settlement updated.']);
    }

    /** Approve: post the EOS accrual and clawback/settle the staff member's loans. */
    public function approve(Request $request, StaffSettlement $staffSettlement): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        if ($staffSettlement->status !== StaffSettlement::STATUS_DRAFT) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Settlement is not in draft.']);
        }

        DB::transaction(function () use ($staffSettlement, $request) {
            $staffSettlement->forceFill([
                'status' => StaffSettlement::STATUS_APPROVED,
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now(),
            ])->save();

            // Net off (settle) the user's active loans against the clawback.
            if ((float) $staffSettlement->loan_clawback > 0) {
                StaffLoan::where('user_id', $staffSettlement->user_id)
                    ->where('status', StaffLoan::STATUS_ACTIVE)
                    ->update(['outstanding_amount' => 0, 'status' => StaffLoan::STATUS_SETTLED]);
            }

            $this->accounting->recordSettlementAccrual($staffSettlement->fresh(), $request->user()->id);
        });

        return back()->with('flash', ['type' => 'success', 'message' => 'Settlement approved and accrued.']);
    }

    public function markPaid(Request $request, StaffSettlement $staffSettlement): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canManage($request)) {
            abort(403);
        }
        if ($staffSettlement->status !== StaffSettlement::STATUS_APPROVED) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only approved settlements can be paid.']);
        }
        $data = $request->validate(['payment_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id']]);

        $staffSettlement->forceFill([
            'status' => StaffSettlement::STATUS_PAID,
            'payment_account_id' => (int) $data['payment_account_id'],
            'paid_at' => now(),
        ])->save();
        // Deactivate the staff member's salary profile on final settlement.
        StaffCompensationProfile::where('user_id', $staffSettlement->user_id)
            ->update(['is_active' => false, 'termination_date' => $staffSettlement->last_working_day]);

        $this->accounting->recordSettlementPayment($staffSettlement->fresh(), $request->user()->id);

        return back()->with('flash', ['type' => 'success', 'message' => 'Settlement paid.']);
    }

    public function destroy(Request $request, StaffSettlement $staffSettlement): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $request->user()->can('delete_staff_settlements')) {
            abort(403);
        }
        if ($staffSettlement->status !== StaffSettlement::STATUS_DRAFT) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only draft settlements can be deleted.']);
        }
        $staffSettlement->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Settlement removed.']);
    }

    protected function net(array $d): float
    {
        return round(
            (float) ($d['gratuity_amount'] ?? 0)
            + (float) ($d['leave_encashment'] ?? 0)
            + (float) ($d['other_additions'] ?? 0)
            - (float) ($d['loan_clawback'] ?? 0)
            - (float) ($d['other_deductions'] ?? 0),
            3,
        );
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'last_working_day' => ['required', 'date'],
            'years_of_service' => ['required', 'numeric', 'min:0'],
            'basic_salary_snapshot' => ['required', 'numeric', 'min:0'],
            'gratuity_amount' => ['required', 'numeric', 'min:0'],
            'leave_encashment' => ['nullable', 'numeric', 'min:0'],
            'other_additions' => ['nullable', 'numeric', 'min:0'],
            'loan_clawback' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'user_id' => $data['user_id'],
            'last_working_day' => $data['last_working_day'],
            'years_of_service' => round((float) $data['years_of_service'], 3),
            'basic_salary_snapshot' => round((float) $data['basic_salary_snapshot'], 3),
            'gratuity_amount' => round((float) $data['gratuity_amount'], 3),
            'leave_encashment' => round((float) ($data['leave_encashment'] ?? 0), 3),
            'other_additions' => round((float) ($data['other_additions'] ?? 0), 3),
            'loan_clawback' => round((float) ($data['loan_clawback'] ?? 0), 3),
            'other_deductions' => round((float) ($data['other_deductions'] ?? 0), 3),
            'notes' => $data['notes'] ?? null,
        ];
    }

    protected function paymentAccountOptions(): array
    {
        return Account::query()
            ->where(fn ($q) => $q->where('code', 'like', '111%')->orWhere('code', 'like', '112%')->orWhere('code', 'like', '113%'))
            ->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'name' => $a->code.' · '.$a->name])->all();
    }
}
