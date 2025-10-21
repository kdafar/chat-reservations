<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    public function confirmFromHold(int $branchId, string $msisdn, string $slotKey, int $partySize): array
    {
        [$date,$time,$size,$branch] = explode('@', $slotKey);
        $code = strtoupper(Str::random(6));

        $id = DB::table('bookings')->insertGetId([
            'branch_id'=>$branchId, 'msisdn'=>$msisdn, 'party_size'=>$partySize,
            'res_date'=>$date, 'res_time'=>$time, 'status'=>'confirmed',
            'booking_code'=>$code, 'created_at'=>now(), 'updated_at'=>now(),
        ]);

        $branchName = \App\Models\Branch::find($branchId)?->name ?? 'Branch';
        return [
            'id'=>$id, 'code'=>$code, 'date'=>$date, 'time'=>$time,
            'date_fmt'=>\Carbon\Carbon::parse($date)->isoFormat('ddd, MMM D'),
            'time_fmt'=>date('g:i A', strtotime($time)),
            'branch_name'=>$branchName,
        ];
    }
}
