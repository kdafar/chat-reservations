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

            // No auth (CLI/jobs/etc.) -> don't scope
            if (! $user) {
                return;
            }

            // ✅ Admin bypass: see everything
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return;
            }

            $table = $query->getModel()->getTable();

            // Prevent recursion / weirdness on these core tables
            if (in_array($table, ['branches', 'branch_user'], true)) {
                return;
            }

            static $branchIdCache = [];
            static $doctorIdCache = [];

            // -------------------------
            // 1) Branch scope
            // -------------------------
            if (Schema::hasColumn($table, 'branch_id')) {
                $branchId = $branchIdCache[$user->id]
                    ??= DB::table('branch_user')->where('user_id', $user->id)->value('branch_id');

                if (! $branchId) {
                    $query->whereRaw('1=0');

                    return;
                }

                $query->where($table.'.branch_id', $branchId);
            }

            // -------------------------
            // 2) Doctor scope (doctor users only)
            // -------------------------
            $doctorId = $doctorIdCache[$user->id]
                ??= DB::table('doctors')->where('user_id', $user->id)->value('id');

            if (! $doctorId) {
                return;
            }

            $doctorIdTables = [
                'bookings',
                'doctor_compensation_ledgers',
                'doctor_compensation_profiles',
                'doctor_shifts',
                'follow_up_plans',
                'visits',
            ];

            if (in_array($table, $doctorIdTables, true) && Schema::hasColumn($table, 'doctor_id')) {
                $query->where($table.'.doctor_id', $doctorId);

                return;
            }

            if ($table === 'doctors' && Schema::hasColumn($table, 'user_id')) {
                $query->where($table.'.user_id', $user->id);

                return;
            }
        });
    }
}
