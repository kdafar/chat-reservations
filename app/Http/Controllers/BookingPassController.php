<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\QrPassService;

class BookingPassController
{
    public function show(string $code, QrPassService $qr)
    {
        $booking = Booking::where('booking_code', $code)->firstOrFail();
        $qr->ensureToken($booking);

        return view('booking.pass', [
            'booking' => $booking,
            'qrPngUrl' => route('bookings.qr', ['token' => $booking->qr_token]),
        ]);
    }
}
