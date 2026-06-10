<?php

namespace App\Services\Clinic;

use App\Models\ClinicPaymentMethod;

/**
 * Resolves the ordered list of enabled payment methods for a given branch.
 *
 * Three scope layers exist for a method `key`, most specific wins:
 *   1. branch row  (branch_id = $branchId)                 -> branch override
 *   2. clinic row  (partner_id = $partnerId, branch null)  -> clinic default
 *   3. global row  (partner_id null, branch null)          -> system default
 *
 * Dedupe is BY KEY: if a clinic defines its own 'cash' it overrides the global
 * 'cash'; a branch 'cash' overrides both. Only active rows are considered.
 * Result is ordered by sort_order then key.
 *
 * Dependency-light by design: a single query + an in-PHP merge, no other
 * services required, so it is cheap to call per visit-modal render.
 */
class ClinicPaymentMethodResolver
{
    /**
     * @return array<int, array{key:string,label:string,type:string,requires_reference:bool}>
     */
    public function forBranch(int $branchId, ?int $partnerId): array
    {
        // Pull every candidate row for the three scopes in one query. We sort so
        // that GLOBAL rows come first, then CLINIC, then BRANCH — that way when
        // we fold into a keyed map the more specific scope simply overwrites the
        // less specific one.
        $candidates = ClinicPaymentMethod::query()
            ->where('is_active', true)
            ->where(function ($q) use ($branchId, $partnerId) {
                // 1) branch-specific
                $q->where('branch_id', $branchId);
                // 2) clinic-wide (no branch)
                if ($partnerId !== null) {
                    $q->orWhere(function ($q2) use ($partnerId) {
                        $q2->where('partner_id', $partnerId)->whereNull('branch_id');
                    });
                }
                // 3) global defaults
                $q->orWhere(function ($q3) {
                    $q3->whereNull('partner_id')->whereNull('branch_id');
                });
            })
            // Stable ordering so the key-fold is deterministic even if two rows
            // share a scope+key (the write-time guard prevents that, but this
            // protects legacy data): same rank → lowest id wins.
            ->orderBy('id')
            ->get();

        // Specificity rank: branch (3) > clinic (2) > global (1).
        $rankOf = function (ClinicPaymentMethod $m): int {
            if (! empty($m->branch_id)) {
                return 3;
            }
            if (! empty($m->partner_id)) {
                return 2;
            }

            return 1;
        };

        // Fold by key, keeping the most specific row for each key. Strict '>'
        // means a same-rank duplicate does NOT overwrite — combined with the
        // id ordering above, the lowest-id row wins its scope deterministically.
        $winners = [];
        foreach ($candidates as $m) {
            $existing = $winners[$m->key] ?? null;
            if ($existing === null || $rankOf($m) > $rankOf($existing)) {
                $winners[$m->key] = $m;
            }
        }

        // Order by sort_order then key, then project to the public shape.
        $rows = array_values($winners);
        usort($rows, function (ClinicPaymentMethod $a, ClinicPaymentMethod $b) {
            return [$a->sort_order, $a->key] <=> [$b->sort_order, $b->key];
        });

        return array_map(fn (ClinicPaymentMethod $m) => [
            'key' => (string) $m->key,
            'label' => (string) $m->label,
            'type' => (string) $m->type,
            'requires_reference' => (bool) $m->requires_reference,
        ], $rows);
    }
}
