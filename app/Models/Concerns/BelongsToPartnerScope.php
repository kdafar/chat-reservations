<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Clinic (partner) isolation for models that carry a partner_id but NOT a
 * branch_id — chiefly patients, who belong to a clinic and can be seen across
 * that clinic's branches. Mirrors BelongsToBranchScope: super_admin / admin
 * see everything; everyone else is limited to the clinic(s) they belong to
 * (via partner_user, their branches' partner, or their doctor profile's branch
 * partner). Rows with a null partner_id stay shared/visible.
 */
trait BelongsToPartnerScope
{
    protected static array $bpsAdminCache = [];

    protected static array $bpsPartnerIdsCache = [];

    protected static function bootBelongsToPartnerScope(): void
    {
        static::addGlobalScope('partner', function (Builder $query) {
            $user = auth()->user();

            // No auth (CLI/jobs) -> don't scope.
            if (! $user) {
                return;
            }

            $userId = (int) $user->id;

            // Global admins see every clinic.
            if (! array_key_exists($userId, self::$bpsAdminCache)) {
                self::$bpsAdminCache[$userId] = method_exists($user, 'hasRole')
                    && $user->hasRole(['admin', 'super_admin']);
            }
            if (self::$bpsAdminCache[$userId]) {
                return;
            }

            if (! array_key_exists($userId, self::$bpsPartnerIdsCache)) {
                $direct = DB::table('partner_user')->where('user_id', $userId)->pluck('partner_id');
                $viaBranch = DB::table('branches')
                    ->join('branch_user', 'branches.id', '=', 'branch_user.branch_id')
                    ->where('branch_user.user_id', $userId)
                    ->pluck('branches.partner_id');
                $viaDoctor = DB::table('doctors')
                    ->join('branches', 'doctors.branch_id', '=', 'branches.id')
                    ->where('doctors.user_id', $userId)
                    ->pluck('branches.partner_id');

                self::$bpsPartnerIdsCache[$userId] = $direct->merge($viaBranch)->merge($viaDoctor)
                    ->filter()->unique()->values()->all();
            }

            $ids = self::$bpsPartnerIdsCache[$userId];
            $table = $query->getModel()->getTable();

            if (empty($ids)) {
                // No clinic membership -> only shared (null-partner) rows.
                $query->whereNull("{$table}.partner_id");
            } else {
                $query->where(function (Builder $q) use ($table, $ids) {
                    $q->whereIn("{$table}.partner_id", $ids)->orWhereNull("{$table}.partner_id");
                });
            }
        });
    }
}
