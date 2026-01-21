<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\QrPassService;

class BookingQrController
{
    public function image(string $token, QrPassService $qr)
    {
        $booking = Booking::where('qr_token', $token)->firstOrFail();

        return $qr->qrPngResponse($booking, 600);
    }
}
