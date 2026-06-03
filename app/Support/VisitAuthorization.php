<?php

namespace App\Support;

use App\Models\Doctor;
use App\Models\Visit;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Mirrors the role-based gating Filament uses for the WaitingPatients
 * console (see App\Filament\Pages\WaitingPatients::canOperateVisit).
 *
 * Use as a trait on controllers that touch visits so the rules stay in
 * one place rather than drifting between Filament and v2.
 */
trait VisitAuthorization
{
    protected function authUser(): ?Authenticatable
    {
        return auth()->user();
    }

    protected function isAdminUser(): bool
    {
        $u = $this->authUser();

        return (bool) ($u && method_exists($u, 'hasRole')
            && ($u->hasRole('super_admin') || $u->hasRole('admin') || $u->hasRole('clinic_admin')));
    }

    protected function isReceptionUser(): bool
    {
        $u = $this->authUser();

        return (bool) ($u && method_exists($u, 'hasRole')
            && ($u->hasRole('clinic_reception') || $this->isAdminUser()));
    }

    protected function doctorIdForCurrentUser(): ?int
    {
        $uid = (int) (auth()->id() ?? 0);
        if ($uid <= 0) {
            return null;
        }
        $id = (int) (Doctor::query()->where('user_id', $uid)->value('id') ?: 0);

        return $id > 0 ? $id : null;
    }

    protected function isDoctorUser(): bool
    {
        return $this->doctorIdForCurrentUser() !== null;
    }

    /**
     * Can the current user perform clinical actions on this visit?
     * - admin / super_admin / clinic_admin: yes
     * - doctor: only on visits where visit.doctor_id matches AND the
     *   doctor's branch_id matches visit.branch_id (so a doctor in
     *   Branch A can't operate visits in Branch B even if doctor_id
     *   happens to match — defends against multi-branch doctor setups)
     * - everyone else: no
     */
    protected function canOperateVisit(Visit $visit): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        $doctorId = $this->doctorIdForCurrentUser();
        if ($doctorId === null || (int) $visit->doctor_id !== (int) $doctorId) {
            return false;
        }

        // Branch ownership: doctor's branch must match the visit's branch.
        // If either side has no branch set we skip this check (legacy data).
        $doctorBranchId = (int) (Doctor::query()->where('id', $doctorId)->value('branch_id') ?: 0);
        $visitBranchId = (int) ($visit->branch_id ?? 0);
        if ($doctorBranchId > 0 && $visitBranchId > 0 && $doctorBranchId !== $visitBranchId) {
            return false;
        }

        return true;
    }

    /**
     * Can the current user collect / record payments on this visit?
     * Admin or reception (clinic_reception).
     */
    protected function canCollectPayment(Visit $visit): bool
    {
        return $this->isAdminUser() || $this->isReceptionUser();
    }

    /**
     * Can the current user perform reception actions on a booking?
     * (check-in, collect consultation fee, assign room, reschedule,
     * cancel, no-show, resend confirmation, create a manual booking)
     */
    protected function canManageBooking(): bool
    {
        return $this->isAdminUser() || $this->isReceptionUser();
    }

    /**
     * Strict front-desk role set — only these can see pending check-ins
     * on the waiting queue. Deliberately tighter than canManageBooking()
     * (clinic_admin is excluded — they manage configuration, not the desk).
     */
    protected function canSeePendingCheckins(): bool
    {
        $u = $this->authUser();

        return (bool) ($u && method_exists($u, 'hasRole')
            && ($u->hasRole('super_admin') || $u->hasRole('admin') || $u->hasRole('clinic_reception')));
    }

    /**
     * Visit is in a terminal (closed) status.
     * Mirrors BookingResource::isTerminal logic for visits.
     */
    protected function visitIsTerminal(Visit $visit): bool
    {
        return in_array($visit->status, [
            Visit::STATUS_COMPLETED,
            Visit::STATUS_CANCELLED,
            Visit::STATUS_NO_SHOW,
        ], true);
    }

    /** True iff reception has stamped checked_in_at. */
    protected function visitIsCheckedIn(Visit $visit): bool
    {
        return ! empty($visit->checked_in_at);
    }

    /**
     * Statuses where doctors may edit clinical notes, add/remove services,
     * add/remove items, etc. Mirrors Filament WaitingPatients::addPackages
     * visibility — only the live treatment window:
     *   awaiting_doctor → in_progress → awaiting_stock
     *
     * NOT `created` (before check-in), NOT `awaiting_payment` (reception's
     * billing zone), NOT terminal.
     */
    protected function visitAcceptsClinicalEdits(Visit $visit): bool
    {
        if (! $this->visitIsCheckedIn($visit)) {
            return false;
        }

        return in_array($visit->status, [
            Visit::STATUS_AWAITING_DOCTOR,
            Visit::STATUS_IN_PROGRESS,
            Visit::STATUS_AWAITING_STOCK,
        ], true);
    }

    /**
     * Statuses where reception may record a payment.
     * Mirrors Filament `collect_visit_payment` visibility:
     *   isCheckedIn && !isTerminal && visitIsOpen
     *
     * So: any non-terminal status after check-in, including awaiting_payment
     * (that's exactly when reception collects the remaining balance).
     */
    protected function visitAcceptsPayments(Visit $visit): bool
    {
        if (! $this->visitIsCheckedIn($visit)) {
            return false;
        }

        if ($this->visitIsTerminal($visit)) {
            return false;
        }

        // Filament's visitIsOpen also rejects completed_at IS NOT NULL.
        return empty($visit->completed_at);
    }
}
