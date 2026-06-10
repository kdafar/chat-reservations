<?php

namespace App\Services\Clinic;

use App\Models\GatewayAccount;
use App\Models\Visit;
use App\Services\Payment\MyFatoorahService;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Generates a MyFatoorah payment link for a visit's outstanding balance and a
 * scannable QR for it. The link's callback points at the visit finalizer
 * (PaymentCallbackController::finalizeVisit) which records the VisitPayment when
 * MyFatoorah confirms the charge — so we never trust the browser, only the API.
 */
class VisitPaymentLinkService
{
    public function __construct(protected VisitCostingService $costing) {}

    /**
     * @return array{url:string, account_id:int, amount:float, qr_svg:string}
     */
    public function createForVisit(Visit $visit, ?float $amount = null, string $kind = 'other'): array
    {
        $branchId = (int) ($visit->branch_id ?? 0);
        $partnerId = (int) ($visit->branch?->partner_id ?? 0);

        $account = GatewayAccount::bestForBranch($branchId, $partnerId ?: null);
        if (! $account) {
            throw new RuntimeException('No online payment gateway is configured for this branch.');
        }

        // Default to the live outstanding balance; callers may override (e.g. a
        // partial deposit). Round to fils — MyFatoorah rejects junk precision.
        $amount = $amount !== null ? round($amount, 3) : round($this->costing->getRemainingBalance($visit), 3);
        if ($amount <= 0) {
            throw new RuntimeException('Nothing to pay — the balance is already settled.');
        }

        $patient = $visit->patient;
        $sig = MyFatoorahService::accountSig((string) $account->id);

        $url = (new MyFatoorahService($account->credentials))->createInvoice([
            'account_id' => $account->id,
            'name' => $patient?->name ?: 'Patient',
            'phone' => (string) ($patient?->phone ?? $visit->booking?->msisdn ?? ''),
            'amount' => $amount,
            // The finalizer parses "VISIT-{id}|{kind}" back out of CustomerReference.
            'ref_id' => 'VISIT-'.$visit->id.'|'.$kind,
            'callback_url' => route('visits.payment.finalize', [
                'account_id' => $account->id,
                'sig' => $sig,
            ]),
            'error_url' => route('visits.payment.failed', [
                'account_id' => $account->id,
                'sig' => $sig,
            ]),
        ]);

        return [
            'url' => $url,
            'account_id' => (int) $account->id,
            'amount' => $amount,
            'qr_svg' => $this->qrDataUri($url),
        ];
    }

    /** Render the link as an inline SVG data-URI so the UI can show it as <img>. */
    protected function qrDataUri(string $url): string
    {
        $svg = (string) QrCode::format('svg')->size(220)->margin(1)->errorCorrection('M')->generate($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
