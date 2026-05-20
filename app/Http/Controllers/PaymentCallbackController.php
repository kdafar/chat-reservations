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

        if (! $paymentId || ! $accountId) {
            return $this->failed($request, 'Invalid payment link parameters.');
        }

        // 1. Load Credentials securely
        $gatewayAccount = GatewayAccount::find($accountId);
        if (! $gatewayAccount || empty($gatewayAccount->credentials['api_key'])) {
            return $this->failed($request, 'Clinic payment configuration invalid.');
        }

        // 2. Verify Payment with MyFatoorah API via SDK Service
        $status = $this->checkPaymentStatus($paymentId, $gatewayAccount);

        if (! $status['success']) {
            return $this->failed($request, $status['message']);
        }

        // FIX: Use InvoiceValue from root object (Safer than nested Transaction array)
        $paidAmount = $status['data']['InvoiceValue'] ?? 0;

        // 3. Process the Booking
        // Reference was set as "BKG-{booking_code}"
        $bookingCode = str_replace('BKG-', '', $status['data']['CustomerReference'] ?? '');
        $booking = Booking::where('booking_code', $bookingCode)->first();

        if (! $booking) {
            Log::error("Payment Success but Booking not found: $bookingCode");

            return $this->failed($request, 'Booking record not found. Please contact the clinic.');
        }

        // 4. Record Payment in Database (Idempotent)
        try {
            DB::transaction(function () use ($booking, $status, $paymentId, $paidAmount) {
                // Ensure a Visit exists to attach payment to (Aligned with BookingResource logic)
                $visit = $this->ensureVisit($booking);

                // Prevent duplicate entry
                $exists = VisitPayment::where('reference_no', $paymentId)->exists();

                if (! $exists) {
                    VisitPayment::create([
                        'visit_id' => $visit->id,
                        'amount' => $paidAmount,
                        'method' => 'myfatoorah',
                        'kind' => 'consultation',
                        'status' => 'paid',
                        'reference_no' => $paymentId,
                        'paid_at' => Carbon::parse($status['data']['CreatedDate'] ?? now()),
                        'collected_by_user_id' => null, // System / Online
                        'meta' => $status['data'], // Store full API response for debugging
                    ]);
                }
            });
        } catch (\Exception $e) {
            Log::error('DB Error saving payment: '.$e->getMessage());

            return $this->failed($request, 'Payment received but system update failed. Please show this screen to reception.');
        }

        return view('bookings.payment.success', [
            'booking' => $booking,
            'amount' => $paidAmount,
            'ref' => $paymentId,
        ]);
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
