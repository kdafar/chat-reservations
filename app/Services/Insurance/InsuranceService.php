<?php

namespace App\Services\Insurance;

use App\Models\Insurance\InsuranceClaim;
use App\Models\Insurance\InsuranceClaimItem;
use App\Models\Insurance\InsuranceClaimPayment;
use App\Models\Insurance\InsuranceClaimStateLog;
use App\Models\Insurance\InsuranceCoverageRule;
use App\Models\Insurance\PatientInsurancePolicy;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Application-layer orchestrator for the insurance module.
 *
 * Holds the business rules around claim lifecycle, coverage estimation,
 * insurer payments, and write-offs. Persistence happens here; the
 * calculator stays pure and the state machine stays declarative.
 *
 * Style mirrors AccountingService: constructor DI, transactions around
 * multi-row writes, errors logged with context.
 */
class InsuranceService
{
    public function __construct(
        protected ClaimStateMachine $states,
        protected CoverageCalculator $calculator,
        protected AccountingService $accounting,
    ) {}

    // -------------------------------------------------------------------------
    // POLICY LOOKUP
    // -------------------------------------------------------------------------

    /**
     * Return all currently-active policies for a patient, ordered by priority
     * (primary first → then by effective_from desc as a tiebreaker).
     *
     * @return Collection<int, PatientInsurancePolicy>
     */
    public function activePoliciesFor(Patient $patient): Collection
    {
        return PatientInsurancePolicy::query()
            ->where('patient_id', $patient->getKey())
            ->active()
            ->orderByDesc('is_primary')
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->get();
    }

    public function primaryPolicyFor(Patient $patient): ?PatientInsurancePolicy
    {
        return PatientInsurancePolicy::query()
            ->where('patient_id', $patient->getKey())
            ->where('is_primary', true)
            ->active()
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->first();
    }

    // -------------------------------------------------------------------------
    // ESTIMATION
    // -------------------------------------------------------------------------

    /**
     * Compute coverage estimate for a visit using all the patient's active
     * policies (cascaded primary → secondary).
     *
     * @return array{
     *   by_kind: array<string, array{kind: string, gross: float, patient_copay: float, insurer_portions: array<int, array{policy_id: int, amount: float}>}>,
     *   totals: array{gross: float, patient_total: float, insurer_total: float}
     * }
     */
    public function estimateForVisit(Visit $visit): array
    {
        $patient = $visit->patient;
        if (! $patient) {
            return [
                'by_kind' => [],
                'totals' => ['gross' => 0.0, 'patient_total' => 0.0, 'insurer_total' => 0.0],
            ];
        }

        $policies = $this->activePoliciesFor($patient);

        return $this->calculator->estimate($visit, $policies);
    }

    // -------------------------------------------------------------------------
    // CLAIM CREATION
    // -------------------------------------------------------------------------

    /**
     * Create a draft claim for a visit + policy. Mirrors visit charges/items/
     * packages into claim items with kind buckets matched to the calculator.
     *
     * Idempotent: if a non-void claim already exists for
     * (visit_id, patient_policy_id), return it untouched.
     */
    public function createClaimFromVisit(Visit $visit, PatientInsurancePolicy $policy, User $user): InsuranceClaim
    {
        $existing = InsuranceClaim::query()
            ->where('visit_id', $visit->getKey())
            ->where('patient_policy_id', $policy->getKey())
            ->where('status', '!=', InsuranceClaim::STATUS_VOID)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($visit, $policy, $user) {
                // Estimate using just this single policy so the per-line
                // covered/copay split matches what we're persisting.
                $estimate = $this->calculator->estimate($visit, collect([$policy]));

                $totalGross = (float) ($estimate['totals']['gross'] ?? 0);
                $patientCopay = (float) ($estimate['totals']['patient_total'] ?? 0);
                $insurerPayable = round($totalGross - $patientCopay, 3);

                $claim = new InsuranceClaim;
                $claim->forceFill([
                    'visit_id' => $visit->getKey(),
                    'patient_policy_id' => $policy->getKey(),
                    'branch_id' => $visit->branch_id,
                    'claim_number' => $this->generateClaimNumber(),
                    'status' => InsuranceClaim::STATUS_DRAFT,
                    'total_charged' => round($totalGross, 3),
                    'patient_copay' => round($patientCopay, 3),
                    'insurer_payable' => $insurerPayable,
                    'approved_amount' => 0,
                    'rejected_amount' => 0,
                    'paid_amount' => 0,
                    'write_off_amount' => 0,
                    'submitted_by_user_id' => null,
                ])->save();

                $this->seedClaimItems($claim, $visit, $estimate);

                // Initial state-log row so the history starts at "created".
                InsuranceClaimStateLog::create([
                    'claim_id' => $claim->id,
                    'from_status' => null,
                    'to_status' => InsuranceClaim::STATUS_DRAFT,
                    'changed_by_user_id' => $user->id,
                    'changed_at' => now(),
                    'notes' => 'Claim drafted from visit #'.$visit->getKey(),
                ]);

                return $claim->fresh();
            });
        } catch (\Throwable $e) {
            Log::error('[InsuranceService::createClaimFromVisit] error', [
                'visit_id' => $visit->getKey(),
                'policy_id' => $policy->getKey(),
                'msg' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate a unique claim number: CLM-YYYYMMDD-XXXXX
     *
     * The XXXXX suffix is a monotonic counter of claims created today,
     * zero-padded to 5 digits. Collisions are protected at the DB level
     * by the unique index — we retry a handful of times before giving up.
     */
    public function generateClaimNumber(): string
    {
        $datePart = now()->format('Ymd');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $countToday = InsuranceClaim::query()
                ->where('claim_number', 'like', "CLM-{$datePart}-%")
                ->count();

            $seq = str_pad((string) ($countToday + 1 + $attempt), 5, '0', STR_PAD_LEFT);
            $candidate = "CLM-{$datePart}-{$seq}";

            $taken = InsuranceClaim::query()
                ->where('claim_number', $candidate)
                ->exists();

            if (! $taken) {
                return $candidate;
            }
        }

        // Final fallback: append a short random suffix so we don't loop forever.
        return "CLM-{$datePart}-".strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
    }

    // -------------------------------------------------------------------------
    // STATE TRANSITIONS
    // -------------------------------------------------------------------------

    /**
     * Transition a claim with state-machine validation + audit log.
     *
     * $payload may include: approved_amount, rejected_amount,
     * decision_notes, reference_no, eob_path.
     */
    public function transition(
        InsuranceClaim $claim,
        string $toStatus,
        User $user,
        ?string $notes = null,
        array $payload = []
    ): InsuranceClaim {
        return DB::transaction(function () use ($claim, $toStatus, $user, $notes, $payload) {
            $this->states->assertTransition($claim->status, $toStatus);

            $from = $claim->status;
            $claim->status = $toStatus;

            foreach (['approved_amount', 'rejected_amount', 'decision_notes', 'reference_no', 'eob_path'] as $f) {
                if (array_key_exists($f, $payload)) {
                    $claim->forceFill([$f => $payload[$f]]);
                }
            }

            if ($toStatus === InsuranceClaim::STATUS_SUBMITTED) {
                $claim->forceFill([
                    'submitted_at' => $claim->submitted_at ?? now(),
                    'submitted_by_user_id' => $claim->submitted_by_user_id ?? $user->id,
                ]);
            }

            if (in_array($toStatus, [
                InsuranceClaim::STATUS_APPROVED,
                InsuranceClaim::STATUS_PARTIALLY_APPROVED,
                InsuranceClaim::STATUS_REJECTED,
            ], true)) {
                $claim->forceFill(['decided_at' => $claim->decided_at ?? now()]);
            }

            if ($toStatus === InsuranceClaim::STATUS_PAID) {
                $claim->forceFill(['paid_at' => $claim->paid_at ?? now()]);
            }

            $claim->save();

            InsuranceClaimStateLog::create([
                'claim_id' => $claim->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'changed_by_user_id' => $user->id,
                'changed_at' => now(),
                'notes' => $notes,
            ]);

            if ($from === InsuranceClaim::STATUS_PAID && $toStatus === InsuranceClaim::STATUS_VOID) {
                Log::warning('[InsuranceService] paid claim voided', [
                    'claim_id' => $claim->id,
                    'claim_number' => $claim->claim_number,
                    'voided_by_user_id' => $user->id,
                    'reason' => $notes,
                ]);
            }

            return $claim->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // PAYMENTS
    // -------------------------------------------------------------------------

    /**
     * Record an insurer payment toward a claim.
     *
     * Persists the payment row, asks the accounting service to post the
     * matching JE, then refreshes the claim's paid_amount from the sum of
     * payments. If the resulting balance is ≤ 0.001, auto-transitions to
     * 'paid'.
     */
    public function recordInsurerPayment(
        InsuranceClaim $claim,
        float $amount,
        string $method,
        ?string $referenceNo,
        ?int $depositedToAccountId,
        User $user,
    ): InsuranceClaimPayment {
        if ($amount <= 0) {
            throw new RuntimeException("Insurer payment amount must be positive (got {$amount}).");
        }

        if ($claim->status === InsuranceClaim::STATUS_VOID) {
            throw new RuntimeException("Cannot record payment against a void claim (#{$claim->claim_number}).");
        }

        $payment = DB::transaction(function () use ($claim, $amount, $method, $referenceNo, $depositedToAccountId, $user) {
            $payment = new InsuranceClaimPayment;
            $payment->forceFill([
                'claim_id' => $claim->id,
                'branch_id' => $claim->branch_id,
                'amount' => round($amount, 3),
                'method' => $method,
                'reference_no' => $referenceNo,
                'paid_at' => now(),
                'deposited_to_account_id' => $depositedToAccountId,
                'received_by_user_id' => $user->id,
            ])->save();

            // Refresh paid_amount from the canonical source: the payments table.
            $paidSum = InsuranceClaimPayment::query()
                ->where('claim_id', $claim->id)
                ->sum('amount');

            $claim->forceFill([
                'paid_amount' => round((float) $paidSum, 3),
            ])->save();

            return $payment->fresh();
        });

        // Post the matching journal entry. Failure here logs but doesn't
        // break the payment record (mirrors AccountingService's philosophy).
        try {
            $this->accounting->recordInsurerPayment($payment);
        } catch (\Throwable $e) {
            Log::error('[InsuranceService::recordInsurerPayment] accounting post failed', [
                'payment_id' => $payment->id,
                'claim_id' => $claim->id,
                'msg' => $e->getMessage(),
            ]);
        }

        // Auto-transition to paid if fully settled. We reload to pick up the
        // updated paid_amount written inside the transaction above.
        $fresh = $claim->fresh();
        if ($fresh
            && $fresh->balanceDue() <= 0.001
            && $this->states->canTransition($fresh->status, InsuranceClaim::STATUS_PAID)
        ) {
            try {
                $this->transition(
                    $fresh,
                    InsuranceClaim::STATUS_PAID,
                    $user,
                    'Auto-transitioned after full payment'
                );
            } catch (\Throwable $e) {
                Log::warning('[InsuranceService::recordInsurerPayment] auto-transition to paid skipped', [
                    'claim_id' => $claim->id,
                    'from' => $fresh->status,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return $payment->fresh();
    }

    /**
     * Write off a balance on a claim. Updates write_off_amount, appends a
     * state-log row (no status change), then asks accounting to post
     * Dr Bad Debt Expense / Cr AR Insurance.
     */
    public function writeOff(InsuranceClaim $claim, float $amount, string $reason, User $user): void
    {
        if ($amount <= 0) {
            throw new RuntimeException("Write-off amount must be positive (got {$amount}).");
        }

        if ($claim->status === InsuranceClaim::STATUS_VOID) {
            throw new RuntimeException("Cannot write off a void claim (#{$claim->claim_number}).");
        }

        DB::transaction(function () use ($claim, $amount, $reason, $user) {
            $newWriteOff = round((float) $claim->write_off_amount + $amount, 3);

            $claim->forceFill([
                'write_off_amount' => $newWriteOff,
            ])->save();

            InsuranceClaimStateLog::create([
                'claim_id' => $claim->id,
                'from_status' => $claim->status,
                'to_status' => $claim->status,
                'changed_by_user_id' => $user->id,
                'changed_at' => now(),
                'notes' => 'Write-off '.number_format($amount, 3)." KWD: {$reason}",
                'meta' => [
                    'write_off_amount' => round($amount, 3),
                    'write_off_total' => $newWriteOff,
                ],
            ]);
        });

        try {
            $this->accounting->recordClaimWriteOff($claim->fresh(), round($amount, 3), $reason, $user);
        } catch (\Throwable $e) {
            Log::error('[InsuranceService::writeOff] accounting post failed', [
                'claim_id' => $claim->id,
                'amount' => $amount,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // INTERNAL HELPERS
    // -------------------------------------------------------------------------

    /**
     * Seed insurance_claim_items rows from a visit using the estimate's
     * per-kind buckets as the source of truth for claimed vs copay amounts.
     *
     * For each kind we create one consolidated claim_item line. Per-charge
     * line resolution can be added later — the schema (source_type/source_id)
     * already supports it.
     */
    protected function seedClaimItems(InsuranceClaim $claim, Visit $visit, array $estimate): void
    {
        $byKind = $estimate['by_kind'] ?? [];

        foreach ($byKind as $kind => $bucket) {
            $gross = (float) ($bucket['gross'] ?? 0);
            if ($gross <= 0) {
                continue;
            }

            $patientCopay = (float) ($bucket['patient_copay'] ?? 0);
            $insurerTotal = round(array_sum(array_column($bucket['insurer_portions'] ?? [], 'amount')), 3);

            $label = $this->labelForKind($kind);

            $item = new InsuranceClaimItem;
            $item->forceFill([
                'claim_id' => $claim->id,
                'source_type' => Visit::class,
                'source_id' => $visit->getKey(),
                'kind' => $kind,
                'label' => $label,
                'qty' => 1,
                'unit_price_snapshot' => round($gross, 3),
                'line_total' => round($gross, 3),
                'claimed_amount' => round($insurerTotal, 3),
                'approved_amount' => 0,
                'patient_copay_amount' => round($patientCopay, 3),
            ])->save();
        }
    }

    protected function labelForKind(string $kind): string
    {
        return match ($kind) {
            InsuranceCoverageRule::KIND_CONSULTATION => 'Consultation fees',
            InsuranceCoverageRule::KIND_SERVICES => 'Services / packages',
            InsuranceCoverageRule::KIND_MEDICINES => 'Medicines / consumables',
            InsuranceCoverageRule::KIND_OTHER => 'Other charges',
            default => ucfirst($kind),
        };
    }
}
