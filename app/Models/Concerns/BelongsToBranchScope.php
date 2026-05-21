<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BelongsToBranchScope
{
    /**
     * Per-request caches keyed by user id (or '_no_user' / 'cols:<table>').
     * Avoids hammering branch_user / partner_user / information_schema on
     * every single model query during a Filament dashboard render.
     */
    protected static array $bbsAdminCache = [];

    protected static array $bbsBranchIdsCache = [];

    protected static array $bbsDoctorIdCache = [];

    protected static array $bbsHasColumnCache = [];

    protected static function bootBelongsToBranchScope(): void
    {
        static::addGlobalScope('branch_and_doctor', function (Builder $query) {
            $user = auth()->user();

            // 1. Guard: No auth (CLI/jobs/etc.) -> don't scope
            if (! $user) {
                return;
            }

            // 2. Guard: Admin -> Show all (cached per request)
            $userId = (int) $user->id;
            if (! array_key_exists($userId, self::$bbsAdminCache)) {
                self::$bbsAdminCache[$userId] = method_exists($user, 'hasRole')
                    && $user->hasRole(['admin', 'super_admin']);
            }
            if (self::$bbsAdminCache[$userId]) {
                return;
            }

            $table = $query->getModel()->getTable();

            // Prevent recursion / weirdness on these core structure tables
            if (in_array($table, ['branches', 'branch_user', 'partners', 'partner_user'], true)) {
                return;
            }

            // -------------------------
            // A. BRANCH SCOPE (Staff + Managers + Doctor's own branch)
            // -------------------------
            if (self::bbsHasColumn($table, 'branch_id')) {

                // Cached branch-ids the user can access (staff + partner-manager
                // + the branch their doctor profile lives in, if any).
                if (! array_key_exists($userId, self::$bbsBranchIdsCache)) {
                    $staffBranchIds = DB::table('branch_user')
                        ->where('user_id', $userId)
                        ->pluck('branch_id');

                    $managerBranchIds = DB::table('branches')
                        ->join('partner_user', 'branches.partner_id', '=', 'partner_user.partner_id')
                        ->where('partner_user.user_id', $userId)
                        ->pluck('branches.id');

                    $doctorBranchIds = DB::table('doctors')
                        ->where('user_id', $userId)
                        ->whereNotNull('branch_id')
                        ->pluck('branch_id');

                    self::$bbsBranchIdsCache[$userId] = $staffBranchIds
                        ->merge($managerBranchIds)
                        ->merge($doctorBranchIds)
                        ->unique()
                        ->values()
                        ->all();
                }

                $accessibleBranchIds = self::$bbsBranchIdsCache[$userId];

                if (empty($accessibleBranchIds)) {
                    // Security: no branch access -> no rows. But still allow
                    // global rows (branch_id IS NULL) so system-shared data
                    // like gateway_accounts and global clinic_items remain
                    // visible to the user.
                    $query->where(function (Builder $q) use ($table) {
                        $q->whereNull("{$table}.branch_id")->whereRaw('0 = 1');
                    });
                } else {
                    // Allow either the user's branches OR rows shared with all
                    // branches (branch_id IS NULL, e.g. system-owned).
                    $query->where(function (Builder $q) use ($table, $accessibleBranchIds) {
                        $q->whereIn("{$table}.branch_id", $accessibleBranchIds)
                            ->orWhereNull("{$table}.branch_id");
                    });
                }
            }

            // -------------------------
            // B. DOCTOR SCOPE (Doctor users only)
            // -------------------------
            if (! array_key_exists($userId, self::$bbsDoctorIdCache)) {
                self::$bbsDoctorIdCache[$userId] = (int) DB::table('doctors')
                    ->where('user_id', $userId)
                    ->value('id') ?: null;
            }

            $doctorId = self::$bbsDoctorIdCache[$userId];

            if ($doctorId) {
                $doctorIdTables = [
                    'bookings',
                    'doctor_compensation_ledgers',
                    'doctor_compensation_profiles',
                    'doctor_shifts',
                    'follow_up_plans',
                    'visits',
                ];

                if (in_array($table, $doctorIdTables, true) && self::bbsHasColumn($table, 'doctor_id')) {
                    $query->where("{$table}.doctor_id", $doctorId);
                }

                // If querying the doctors table itself, limit to self
                if ($table === 'doctors' && self::bbsHasColumn($table, 'user_id')) {
                    $query->where("{$table}.user_id", $userId);
                }
            }
        });
    }

    /**
     * Schema::hasColumn() hits information_schema every call. Cache per request.
     */
    protected static function bbsHasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        if (! array_key_exists($key, self::$bbsHasColumnCache)) {
            self::$bbsHasColumnCache[$key] = Schema::hasColumn($table, $column);
        }

        return self::$bbsHasColumnCache[$key];
    }
}
