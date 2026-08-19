<?php

namespace Database\Seeders\Demo;

use App\Models\Accounting\Account;
use App\Models\Insurance\InsuranceClaim;
use App\Models\User;
use App\Services\Insurance\InsuranceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Walks auto-drafted claims through a realistic claims process.
 *
 * Every insured visit auto-drafts a claim through the visit observer, so the
 * system ended up with hundreds of claims sitting in `draft` and never
 * submitted. The insurance report read that faithfully — a 0.5% approval rate —
 * which is right about the data and useless as a picture of the business.
 *
 * This submits them, decides them with the rejection mix a real payer produces,
 * and settles most of the approved ones, leaving a believable tail outstanding
 * for the aging and follow-up screens to chase.
 */
class DemoClaimLifecycleSeeder extends Seeder
{
    /** Rejection reasons a Kuwait clinic actually sees on an EOB. */
    protected array $rejections = [
        'Service excluded under the policy schedule — cosmetic indication.',
        'Pre-authorisation was required and not obtained before treatment.',
        'Patient exceeded the annual benefit limit for this category.',
        'Policy lapsed on the date of service.',
        'Diagnosis code does not support the billed procedure.',
        'Duplicate submission — already settled under an earlier claim.',
        'Supporting clinical notes were not attached.',
    ];

    protected array $partials = [
        'Approved for the consultation only; the procedure needs a clinical review.',
        'Approved at the contracted tariff, which is below the billed amount.',
        'Two of the requested sessions approved; remainder pending justification.',
        'Copay and deductible applied per the policy schedule.',
    ];

    public function run(): void
    {
        DB::connection()->disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $user = User::query()->whereHas('roles', fn ($q) => $q->whereIn('name', ['accountant', 'clinic_admin', 'admin']))->first()
            ?? User::query()->first();
        if (! $user) {
            return;
        }

        $service = app(InsuranceService::class);
        $bank = Account::query()->where('code', '1120')->value('id');

        // Capture the true clock before anything mocks it — every "has this
        // happened yet?" check below has to measure against real today, not
        // against the backdated now() used to stamp the row.
        Carbon::setTestNow();
        $realNow = Carbon::now();

        $drafts = InsuranceClaim::query()->withoutGlobalScopes()
            ->whereIn('status', [InsuranceClaim::STATUS_DRAFT, InsuranceClaim::STATUS_SUBMITTED])
            ->where('insurer_payable', '>', 0)
            ->orderBy('id')->get();

        if ($drafts->isEmpty()) {
            $this->command?->warn('DemoClaimLifecycleSeeder: no open claims with a payable balance.');

            return;
        }

        $counts = ['submitted' => 0, 'under_review' => 0, 'approved' => 0, 'partial' => 0, 'rejected' => 0, 'paid' => 0, 'failed' => 0];

        foreach ($drafts as $i => $claim) {
            try {
                $this->progress($claim, $i, $service, $user, $bank, $counts, $realNow);
            } catch (\Throwable $e) {
                $counts['failed']++;
                if ($counts['failed'] <= 3) {
                    $this->command?->warn("  claim #{$claim->id}: {$e->getMessage()}");
                }
            }
            Carbon::setTestNow();
        }

        Carbon::setTestNow();
        $this->command?->info(sprintf(
            'DemoClaimLifecycleSeeder: submitted %d · approved %d · partial %d · rejected %d · under review %d · paid %d (%d failed).',
            $counts['submitted'], $counts['approved'], $counts['partial'], $counts['rejected'],
            $counts['under_review'], $counts['paid'], $counts['failed'],
        ));
    }

    protected function progress(InsuranceClaim $claim, int $i, InsuranceService $service, User $user, ?int $bank, array &$counts, Carbon $realNow): void
    {
        // A claim is raised a couple of days after the visit; everything else
        // hangs off that date so the aging buckets spread properly.
        $raised = Carbon::parse($claim->created_at);
        $payable = (float) $claim->insurer_payable;

        // A slice never gets submitted — that backlog is real, and the
        // follow-up board exists precisely to chase it.
        if ($claim->status === InsuranceClaim::STATUS_DRAFT && $i % 17 === 0) {
            return;
        }

        if ($claim->status === InsuranceClaim::STATUS_DRAFT) {
            Carbon::setTestNow($raised->copy()->addDays(random_int(1, 5))->setTime(random_int(9, 16), 0));
            $service->transition($claim, InsuranceClaim::STATUS_SUBMITTED, $user, 'Submitted to the insurer portal.');
            $counts['submitted']++;
        }

        $submittedAt = Carbon::parse($claim->refresh()->submitted_at ?? $raised);

        // Still with the insurer — no decision yet.
        if ($i % 11 === 0) {
            $reviewAt = $submittedAt->copy()->addDays(random_int(3, 20));
            Carbon::setTestNow($reviewAt->gt($realNow) ? $realNow : $reviewAt);
            $service->transition($claim->refresh(), InsuranceClaim::STATUS_UNDER_REVIEW, $user, 'Insurer requested supporting notes.');
            $counts['under_review']++;

            return;
        }

        $decidedAt = $submittedAt->copy()->addDays(random_int(4, 35));
        if ($decidedAt->gt($realNow)) {
            return; // decision genuinely hasn't come back yet
        }
        Carbon::setTestNow($decidedAt);

        $roll = $i % 100;

        if ($roll < 8) {
            $service->transition($claim->refresh(), InsuranceClaim::STATUS_REJECTED, $user, null, [
                'rejected_amount' => $payable,
                'decision_notes' => $this->rejections[$i % count($this->rejections)],
            ]);
            $counts['rejected']++;

            return;
        }

        if ($roll < 26) {
            $approved = round($payable * (random_int(45, 80) / 100), 3);
            $service->transition($claim->refresh(), InsuranceClaim::STATUS_PARTIALLY_APPROVED, $user, null, [
                'approved_amount' => $approved,
                'rejected_amount' => round($payable - $approved, 3),
                'decision_notes' => $this->partials[$i % count($this->partials)],
            ]);
            $counts['partial']++;
        } else {
            $service->transition($claim->refresh(), InsuranceClaim::STATUS_APPROVED, $user, null, [
                'approved_amount' => $payable,
            ]);
            $counts['approved']++;
        }

        // Settlement lands weeks after approval, and not every approved claim
        // has been paid yet — that remainder is what the aging report ages.
        $claim->refresh();
        $balance = round($claim->balanceDue(), 3);
        if ($balance <= 0 || $i % 5 === 0) {
            return;
        }

        $paidAt = $decidedAt->copy()->addDays(random_int(10, 55));
        if ($paidAt->gt($realNow)) {
            return;
        }

        Carbon::setTestNow($paidAt);
        $payment = $service->recordInsurerPayment(
            claim: $claim,
            amount: $balance,
            method: ['transfer', 'transfer', 'cheque'][$i % 3],
            referenceNo: 'REM-'.$paidAt->format('ym').'-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
            depositedToAccountId: $bank,
            user: $user,
        );
        $payment->forceFill(['paid_at' => $paidAt])->saveQuietly();

        // recordInsurerPayment settles the balance; flip the claim to paid too.
        $claim->refresh();
        if ($claim->status !== InsuranceClaim::STATUS_PAID && round($claim->balanceDue(), 3) <= 0.005) {
            $service->transition($claim, InsuranceClaim::STATUS_PAID, $user, 'Remittance received in full.');
        }
        $counts['paid']++;
    }
}
