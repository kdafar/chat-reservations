<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class HoldService
{
    public function create(int $branchId, string $msisdn, string $slotKey, int $ttlMinutes=5): bool
    {
        try {
            DB::table('booking_holds')->insert([
                'branch_id'=>$branchId, 'msisdn'=>$msisdn, 'slot_key'=>$slotKey,
                'expires_at'=>now()->addMinutes($ttlMinutes), 'created_at'=>now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false; // unique(slot_key) collision = race lost
        }
    }

    public function cleanupExpired(): int
    {
        return DB::table('booking_holds')->where('expires_at','<',now())->delete();
    }
}
