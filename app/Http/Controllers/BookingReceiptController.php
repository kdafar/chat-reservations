<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Visit;
use App\Models\VisitPayment;
use Illuminate\Http\Request;

class BookingReceiptController extends Controller
{
    public function show(Request $request, Booking $booking)
    {
        // 1. Security: Ensure user can access this booking
        // The global scope on Booking model handles this, but explicit check is safer
        if (! auth()->user()) {
            abort(403);
        }

        // 2. Load Relationships safely
        $booking->load(['branch.partner', 'doctor', 'patient', 'contact']);

        // 3. Get Financial Context (Visit)
        $visit = Visit::where('booking_id', $booking->id)->first();

        if (! $visit) {
            abort(404, 'No visit record found for this booking.');
        }

        // 4. Find the Payment
        // Priority: Get the specific payment requested via query param, OR the consultation payment
        $payment = null;
        if ($request->has('payment_id')) {
            $payment = VisitPayment::where('visit_id', $visit->id)
                ->where('id', $request->input('payment_id'))
                ->first();
        } else {
            // Default to the Consultation payment
            $payment = VisitPayment::where('visit_id', $visit->id)
                ->where('kind', VisitPayment::KIND_CONSULTATION ?? 'consultation')
                ->where('status', 'paid')
                ->latest()
                ->first();
        }

        if (! $payment) {
            return view('errors.minimal', ['message' => 'No payment record found to print.']);
        }

        // 5. Prepare View Data
        return view('bookings.receipt-print', [
            'booking' => $booking,
            'visit' => $visit,
            'payment' => $payment,
            'partner' => $booking->branch->partner ?? null,
            'doctor' => $booking->doctor,
            'patient' => $booking->patient ?? $booking->contact, // Fallback to contact if patient null
        ]);
    }
}
