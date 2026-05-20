<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait BelongsToBranchScope
{
    protected static function bootBelongsToBranchScope(): void
    {
        static::addGlobalScope('branch_and_doctor', function (Builder $query) {
            $user = auth()->user();

            // 1. Guard: No auth (CLI/jobs/etc.) -> don't scope
            if (! $user) {
                return;
            }

            // 2. Guard: Admin -> Show all
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return;
            }

            $table = $query->getModel()->getTable();

            // Prevent recursion / weirdness on these core structure tables
            // Added 'partners' and 'partner_user' to safety list
            if (in_array($table, ['branches', 'branch_user', 'partners', 'partner_user'], true)) {
                return;
            }

            // -------------------------
            // A. BRANCH SCOPE (Staff + Managers)
            // -------------------------
            if (Schema::hasColumn($table, 'branch_id')) {

                // 1. Get IDs user is explicitly assigned to (Staff)
                // Fixed: Use pluck() instead of value() to support multiple branches
                $staffBranchIds = DB::table('branch_user')
                    ->where('user_id', $user->id)
                    ->pluck('branch_id');

                // 2. Get IDs via Partner ownership (Managers)
                // Logic: If user manages a Partner, they see ALL branches for that Partner.
                $managerBranchIds = DB::table('branches')
                    ->join('partner_user', 'branches.partner_id', '=', 'partner_user.partner_id')
                    ->where('partner_user.user_id', $user->id)
                    ->pluck('branches.id');

                // 3. Merge unique IDs
                $accessibleBranchIds = $staffBranchIds->merge($managerBranchIds)->unique();

                // 4. Apply Filter
                if ($accessibleBranchIds->isEmpty()) {
                    // Security: If user has no access, return 0 results
                    $query->whereRaw('1=0');
                } else {
                    $query->whereIn("{$table}.branch_id", $accessibleBranchIds);
                }
            }

            // -------------------------
            // B. DOCTOR SCOPE (Doctor users only)
            // -------------------------
            // Use static cache to prevent running this query on every model boot within the same request
            static $doctorIdCache = [];

            $doctorId = $doctorIdCache[$user->id]
                ??= DB::table('doctors')->where('user_id', $user->id)->value('id');

            if ($doctorId) {
                $doctorIdTables = [
                    'bookings',
                    'doctor_compensation_ledgers',
                    'doctor_compensation_profiles',
                    'doctor_shifts',
                    'follow_up_plans',
                    'visits',
                ];

                if (in_array($table, $doctorIdTables, true) && Schema::hasColumn($table, 'doctor_id')) {
                    $query->where("{$table}.doctor_id", $doctorId);
                }

                // If querying the doctors table itself, limit to self
                if ($table === 'doctors' && Schema::hasColumn($table, 'user_id')) {
                    $query->where("{$table}.user_id", $user->id);
                }
            }
        });
    }
}
