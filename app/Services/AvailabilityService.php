<?php
namespace App\Services;

use Carbon\Carbon;

class AvailabilityService
{
    public function nextDates(int $branchId, int $days = 5, int $partySize = 2): array
    {
        // TODO: apply branch rules & blackouts
        $out = [];
        $d = now('Asia/Kuwait');
        for ($i=0; $i<$days; $i++) {
            $key = $d->toDateString();
            $out[$key] = $d->isoFormat('ddd, MMM D');
            $d = $d->addDay();
        }
        return $out;
    }

    public function timesFor(int $branchId, string $date, int $size): array
    {
        // TODO: generate from rules, subtract confirmed bookings
        $base = ['18:30','19:00','19:30','20:00','20:30','21:00'];
        return array_map(fn($t)=>['value'=>$t,'label'=>date('g:i A', strtotime($t))], $base);
    }
}
