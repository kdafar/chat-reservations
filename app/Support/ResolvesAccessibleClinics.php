<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Shared clinic (partner) resolution for controllers that must keep one
 * clinic's data out of another's. Matches BelongsToPartnerScope /
 * BelongsToBranchScope: only super_admin / admin are unrestricted.
 */
trait ResolvesAccessibleClinics
{
    /** Only super_admin / admin see across every clinic. */
    protected function isGlobalAdmin(): bool
    {
        $u = auth()->user();

        return (bool) ($u && method_exists($u, 'hasRole') && $u->hasRole(['admin', 'super_admin']));
    }

    /**
     * Partner (clinic) ids the current user may act within — from partner_user,
     * their branches' partner, and their doctor profile's branch partner.
     * Returns null for a global admin (no restriction).
     */
    protected function accessiblePartnerIds(): ?array
    {
        if ($this->isGlobalAdmin()) {
            return null;
        }
        $uid = (int) (auth()->id() ?? 0);

        $direct = DB::table('partner_user')->where('user_id', $uid)->pluck('partner_id');
        $viaBranch = DB::table('branches')
            ->join('branch_user', 'branches.id', '=', 'branch_user.branch_id')
            ->where('branch_user.user_id', $uid)
            ->pluck('branches.partner_id');
        $viaDoctor = DB::table('doctors')
            ->join('branches', 'doctors.branch_id', '=', 'branches.id')
            ->where('doctors.user_id', $uid)
            ->pluck('branches.partner_id');

        return $direct->merge($viaBranch)->merge($viaDoctor)->filter()->unique()->values()->all();
    }

    /**
     * Branch ids the current user may act within (null = global admin = all).
     * Mirrors BelongsToBranchScope's branch logic — the Branch model itself
     * is excluded from that scope (recursion guard), so branch dropdowns must
     * be filtered through this helper to stay clinic-isolated.
     */
    protected function accessibleBranchIds(): ?array
    {
        if ($this->isGlobalAdmin()) {
            return null;
        }
        $uid = (int) (auth()->id() ?? 0);

        $staff = DB::table('branch_user')->where('user_id', $uid)->pluck('branch_id');
        $viaPartner = DB::table('branches')
            ->join('partner_user', 'branches.partner_id', '=', 'partner_user.partner_id')
            ->where('partner_user.user_id', $uid)
            ->pluck('branches.id');
        $viaDoctor = DB::table('doctors')
            ->where('user_id', $uid)
            ->whereNotNull('branch_id')
            ->pluck('branch_id');

        return $staff->merge($viaPartner)->merge($viaDoctor)->map(fn ($x) => (int) $x)->unique()->values()->all();
    }

    /**
     * The partner (clinic) a newly-created catalog row should belong to.
     * Prefers the chosen branch's clinic; else the creator's own clinic.
     * Returns null only for a global admin who picked no branch (= global row).
     */
    protected function defaultPartnerId(?int $branchId = null): ?int
    {
        if ($branchId) {
            $pid = DB::table('branches')->where('id', $branchId)->value('partner_id');
            if ($pid) {
                return (int) $pid;
            }
        }
        $accessible = $this->accessiblePartnerIds(); // null = global admin
        return $accessible[0] ?? null;
    }

    /**
     * Branch {id, name} options scoped to the user (all for a global admin),
     * localized. Use for every branch picker/filter so one clinic's branches
     * never appear in another's forms.
     */
    protected function accessibleBranches(): \Illuminate\Support\Collection
    {
        $ids = $this->accessibleBranchIds();
        $locale = app()->getLocale();

        return \App\Models\Branch::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids ?: [0]))
            ->orderBy('name')
            ->get(['id', 'name', 'partner_id'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => method_exists($b, 'getTranslation') ? $b->getTranslation('name', $locale, true) : $b->name,
                'partner_id' => $b->partner_id,
            ])
            ->values();
    }
}
