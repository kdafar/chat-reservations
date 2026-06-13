<?php

namespace App\Services\Clinic;

use App\Models\StaffLeave;
use App\Models\StaffLeaveEntitlement;
use Illuminate\Support\Carbon;

/**
 * Leave balance = entitled_days + carried_over_days − used_days, where
 * used_days is the sum of APPROVED staff_leaves of the same type whose
 * start date falls in the given year. Used is always computed live so the
 * balance can't drift from the actual approved leave.
 */
class LeaveBalanceService
{
    /**
     * Approved leave days a user has taken of a given type in a year.
     */
    public function usedDays(int $userId, int $year, string $type = 'annual'): int
    {
        return (int) StaffLeave::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('status', StaffLeave::STATUS_APPROVED)
            ->whereYear('starts_on', $year)
            ->sum('days_count');
    }

    /**
     * Pending (undecided) leave days — shown so HR sees committed-but-not-yet
     * approved demand against the balance.
     */
    public function pendingDays(int $userId, int $year, string $type = 'annual'): int
    {
        return (int) StaffLeave::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('status', StaffLeave::STATUS_PENDING)
            ->whereYear('starts_on', $year)
            ->sum('days_count');
    }

    /**
     * Full balance breakdown for one user/year/type.
     *
     * @return array{entitled: float, carried_over: float, used: int, pending: int, remaining: float}
     */
    public function balance(int $userId, int $year, string $type = 'annual'): array
    {
        $ent = StaffLeaveEntitlement::query()
            ->where('user_id', $userId)->where('year', $year)->where('leave_type', $type)->first();

        $entitled = (float) ($ent->entitled_days ?? 0);
        $carried = (float) ($ent->carried_over_days ?? 0);
        $used = $this->usedDays($userId, $year, $type);
        $pending = $this->pendingDays($userId, $year, $type);

        return [
            'entitled' => $entitled,
            'carried_over' => $carried,
            'used' => $used,
            'pending' => $pending,
            'remaining' => round($entitled + $carried - $used, 2),
        ];
    }

    /**
     * Unpaid-leave days taken in a specific calendar month (used by payroll to
     * compute the unpaid-leave salary deduction).
     */
    public function unpaidDaysInMonth(int $userId, int $year, int $month): int
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth();

        return (int) StaffLeave::query()
            ->where('user_id', $userId)
            ->where('type', StaffLeave::TYPE_UNPAID)
            ->where('status', StaffLeave::STATUS_APPROVED)
            // Any approved unpaid leave overlapping the month.
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->get()
            ->sum(function (StaffLeave $l) use ($start, $end) {
                $s = Carbon::parse($l->starts_on)->max($start);
                $e = Carbon::parse($l->ends_on)->min($end);

                return max(0, $s->diffInDays($e) + 1);
            });
    }
}
