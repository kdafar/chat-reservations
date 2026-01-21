<?php

return [
    // Minutes between timeslots
    'slot_interval' => (int) env('BOOKING_SLOT_INTERVAL', 30),

    // Show this many future days in date picker (BookingFlowService can still cap to 3–5)
    'dates_forward_days' => (int) env('BOOKING_DATES_FORWARD_DAYS', 7),

    // Hard guard on party size
    'max_party_size' => (int) env('BOOKING_MAX_PARTY', 12),
];
