<?php

namespace App\Http\Controllers;

use App\Services\CheckInService;
use Illuminate\Http\Request;

class BookingCheckInController
{
    // Staff one-tap from QR link (page visible only if staff is logged in)
    public function fromLink(string $token, CheckInService $svc)
    {
        if (! auth()->check()) {
            // Show a read-only page (no state change) if not staff
            return response('Please ask staff to check you in.', 200);
        }
        $booking = $svc->checkInByToken($token);

        return redirect()->back()->with('status', 'Checked-in: '.$booking->booking_code);
    }

    // POS / scanner app can hit this JSON endpoint
    public function __invoke(Request $r, CheckInService $svc)
    {
        $data = $r->validate([
            'token' => ['required', 'string'],
            'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
        ]);

        $booking = $svc->checkInByToken($data['token'], $data['table_id'] ?? null);

        return response()->json([
            'ok' => true,
            'booking_code' => $booking->booking_code,
            'party_size' => $booking->party_size,
            'branch_id' => $booking->branch_id,
            'table' => $booking->table?->only(['id', 'name', 'capacity']),
            'status' => $booking->status,
            'checked_in_at' => $booking->checked_in_at?->toIso8601String(),
        ]);
    }
}
