<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;

class QrPassService
{
    public function ensureToken(Booking $b): Booking
    {
        if (! $b->qr_token) {
            $b->qr_token = Str::lower(Str::random(20));
            $b->save();
        }

        return $b;
    }

    // Pretty, human-friendly pass page (doesn't change state)
    public function passUrl(Booking $b): string
    {
        return route('bookings.pass', ['code' => $b->booking_code]);
    }

    // Secret check-in link encoded inside the QR (changes state after staff confirm)
    public function checkInUrl(Booking $b): string
    {
        return route('bookings.checkin.link', ['token' => $b->qr_token]);
    }

    public function qrPngResponse(Booking $b, int $size = 600): Response
    {
        $this->ensureToken($b);
        $png = QrCode::format('png')->size($size)->margin(1)->generate($this->checkInUrl($b));

        return response($png, 200, ['Content-Type' => 'image/png']);
    }
}
