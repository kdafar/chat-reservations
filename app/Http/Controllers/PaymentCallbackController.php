<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\GatewayAccount;
use App\Models\Visit;
use App\Models\VisitPayment;
use App\Services\Payment\MyFatoorahService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function finalize(Request $request)
    {
        $paymentId = $request->input('paymentId');
        $accountId = $request->input('account_id');
        $sig = (string) $request->input('sig', '');

        if (! $paymentId || ! $accountId) {
            return $this->failed($request, 'Invalid payment link parameters.');
        }

        // 1. Verify the account_id wasn't tampered with on the way back.
        // Without this, anyone with the callback URL could swap account_id
        // and trigger an API call against a different merchant's account.
        $expectedSig = MyFatoorahService::accountSig((string) $accountId);
        if (! hash_equals($expectedSig, $sig)) {
            Log::warning('[PaymentCallback] invalid account_id signature', [
                'payment_id' => $paymentId,
                'account_id' => $accountId,
            ]);

            return $this->failed($request, 'Invalid payment link signature.');
        }

        // 2. Load Credentials securely
        $gatewayAccount = GatewayAccount::find($accountId);
        if (! $gatewayAccount || empty($gatewayAccount->credentials['api_key'])) {
            return $this->failed($request, 'Clinic payment configuration invalid.');
        }

        // 3. Verify Payment with MyFatoorah API via SDK Service
        $status = $this->checkPaymentStatus($paymentId, $gatewayAccount);

        if (! $status['success']) {
            return $this->failed($request, $status['message']);
        }

        $paidAmount = $status['data']['InvoiceValue'] ?? 0;

        // 4. Process the Booking. Reference was set as "BKG-{booking_code}".
        $bookingCode = str_replace('BKG-', '', $status['data']['CustomerReference'] ?? '');
        $booking = Booking::where('booking_code', $bookingCode)->first();

        if (! $booking) {
            Log::error('[PaymentCallback] booking not found after successful payment', [
                'payment_id' => $paymentId,
                'booking_code' => $bookingCode,
                'account_id' => $accountId,
                'amount' => $paidAmount,
            ]);

            return $this->failed($request, 'Booking record not found. Please contact the clinic.');
        }

        // 5. Record Payment in Database.
        // Idempotency layers:
        //   - updateOrCreate handles serial duplicates (single thread).
        //   - the unique (method, reference_no) index added in
        //     2026_05_20_144820 catches parallel-callback races; we map the
        //     resulting SQLSTATE 23000 to a no-op success path.
        try {
            DB::transaction(function () use ($booking, $status, $paymentId, $paidAmount) {
                $visit = $this->ensureVisit($booking);

                try {
                    VisitPayment::updateOrCreate(
                        [
                            'reference_no' => $paymentId,
                            'method' => 'myfatoorah',
                        ],
                        [
                            'visit_id' => $visit->id,
                            'amount' => $paidAmount,
                            'kind' => 'consultation',
                            'status' => 'paid',
                            'paid_at' => Carbon::parse($status['data']['CreatedDate'] ?? now()),
                            'collected_by_user_id' => null,
                            'meta' => $status['data'],
                        ]
                    );
                } catch (\Illuminate\Database\QueryException $e) {
                    // Race: a parallel callback inserted between our existence
                    // check and our insert. Row IS now in the DB. Treat as
                    // idempotent success.
                    if (($e->errorInfo[0] ?? null) === '23000') {
                        Log::info('[PaymentCallback] duplicate callback (race) — already recorded', [
                            'payment_id' => $paymentId,
                            'booking_code' => $booking->booking_code,
                        ]);

                        return;
                    }
                    throw $e;
                }
            });
        } catch (\Throwable $e) {
            // Money was received from MyFatoorah but our DB write failed.
            // Log loudly so the row can be reconciled by hand.
            Log::critical('[PaymentCallback] money received, DB save failed', [
                'payment_id' => $paymentId,
                'account_id' => $accountId,
                'booking_code' => $bookingCode,
                'amount' => $paidAmount,
                'error' => $e->getMessage(),
                'mf_data' => $status['data'] ?? null,
            ]);

            return $this->failed($request, 'Payment received but system update failed. Please show this screen to reception.');
        }

        return view('bookings.payment.success', [
            'booking' => $booking,
            'amount' => $paidAmount,
            'ref' => $paymentId,
        ]);
    }

    /**
     * Finalizer for VISIT-balance payment links (VisitPaymentLinkService).
     * Same security model as finalize(): verify the account signature, confirm
     * the charge with MyFatoorah's API (never the browser), then idempotently
     * record the VisitPayment. CustomerReference is "VISIT-{id}|{kind}".
     */
    public function finalizeVisit(Request $request)
    {
        $paymentId = $request->input('paymentId');
        $accountId = $request->input('account_id');
        $sig = (string) $request->input('sig', '');

        if (! $paymentId || ! $accountId) {
            return $this->failed($request, 'Invalid payment link parameters.');
        }

        if (! hash_equals(MyFatoorahService::accountSig((string) $accountId), $sig)) {
            Log::warning('[VisitPaymentCallback] invalid account_id signature', ['payment_id' => $paymentId, 'account_id' => $accountId]);

            return $this->failed($request, 'Invalid payment link signature.');
        }

        $gatewayAccount = GatewayAccount::find($accountId);
        if (! $gatewayAccount || empty($gatewayAccount->credentials['api_key'])) {
            return $this->failed($request, 'Clinic payment configuration invalid.');
        }

        $status = $this->checkPaymentStatus($paymentId, $gatewayAccount);
        if (! $status['success']) {
            return $this->failed($request, $status['message']);
        }

        $paidAmount = $status['data']['InvoiceValue'] ?? 0;

        // Reference is "VISIT-{id}|{kind}".
        $ref = (string) ($status['data']['CustomerReference'] ?? '');
        [$visitToken, $kind] = array_pad(explode('|', $ref, 2), 2, 'other');
        $visitId = (int) str_replace('VISIT-', '', $visitToken);
        $kind = in_array($kind, ['consultation', 'services', 'medicines', 'other'], true) ? $kind : 'other';

        $visit = Visit::find($visitId);
        if (! $visit) {
            Log::error('[VisitPaymentCallback] visit not found after successful payment', [
                'payment_id' => $paymentId, 'reference' => $ref, 'amount' => $paidAmount,
            ]);

            return $this->failed($request, 'Visit record not found. Please contact the clinic.');
        }

        try {
            DB::transaction(function () use ($visit, $status, $paymentId, $paidAmount, $kind) {
                try {
                    VisitPayment::updateOrCreate(
                        ['reference_no' => $paymentId, 'method' => 'myfatoorah'],
                        [
                            'visit_id' => $visit->id,
                            'amount' => $paidAmount,
                            'kind' => $kind,
                            'status' => 'paid',
                            'paid_at' => Carbon::parse($status['data']['CreatedDate'] ?? now()),
                            'collected_by_user_id' => null,
                            'meta' => $status['data'],
                        ]
                    );
                } catch (\Illuminate\Database\QueryException $e) {
                    // Parallel-callback race — the unique (method, reference_no)
                    // index already holds the row. Idempotent success.
                    if (($e->errorInfo[0] ?? null) === '23000') {
                        return;
                    }
                    throw $e;
                }
            });
        } catch (\Throwable $e) {
            Log::critical('[VisitPaymentCallback] money received, DB save failed', [
                'payment_id' => $paymentId, 'visit_id' => $visitId, 'amount' => $paidAmount, 'error' => $e->getMessage(),
            ]);

            return $this->failed($request, 'Payment received but system update failed. Please show this screen to reception.');
        }

        return view('payments.visit-success', ['amount' => $paidAmount, 'ref' => $paymentId]);
    }

    public function failed(Request $request, $message = null)
    {
        return view('bookings.payment.failed', [
            'message' => $message ?? 'Payment was cancelled or declined by the bank.',
        ]);
    }

    // --- Helpers ---

    private function checkPaymentStatus($paymentId, $gatewayAccount)
    {
        try {
            // 1. Instantiate Service with the credentials found in DB
            $mfService = new MyFatoorahService($gatewayAccount->credentials);

            // 2. Call the SDK wrapper
            $sdkResponse = $mfService->getPaymentStatus($paymentId);

            // 3. Normalize to Array
            $data = json_decode(json_encode($sdkResponse), true);

            if (isset($data['InvoiceStatus']) && $data['InvoiceStatus'] === 'Paid') {
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            $error = $data['InvoiceError'] ?? $data['InvoiceStatus'] ?? 'Unknown Error';

            return ['success' => false, 'message' => "Payment Unsuccessful: $error"];

        } catch (\Exception $e) {
            Log::error('Payment Verification Exception: '.$e->getMessage());

            return ['success' => false, 'message' => 'Could not verify payment: '.$e->getMessage()];
        }
    }

    private function ensureVisit(Booking $booking): Visit
    {
        // Aligned with BookingResource::ensureVisitForBooking
        $visit = Visit::firstOrNew(['booking_id' => $booking->id]);

        $visit->fill([
            'patient_id' => $booking->patient_id,
            'doctor_id' => $booking->doctor_id,
            'branch_id' => $booking->branch_id,
            'restaurant_table_id' => $booking->table_id,
            'booking_code' => $booking->booking_code,
            'source' => 'online_payment',
        ]);

        if (! in_array(($visit->status ?? null), ['completed', 'no_show', 'cancelled'], true)) {
            $visit->status = $visit->status ?: 'created';
        }

        $visit->save();

        return $visit;
    }
}
