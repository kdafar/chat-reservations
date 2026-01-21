<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HoldService
{
    public function hold(
        int $branchId,
        string $resDate,
        string $resTime,
        int $partySize,
        string $msisdn,
        int $minutes = 5,
        ?int $slotLengthMinutes = null,
        bool $replaceExistingForPhone = true,
    ): ?string {
        $slotKey = "{$resDate}@{$resTime}@{$partySize}@{$branchId}";

        if ($replaceExistingForPhone && $msisdn !== '') {
            $released = $this->releaseByPhone($msisdn);
            Log::info('HoldService: releaseByPhone before hold', [
                'msisdn_tail' => substr($msisdn, -4), 'released' => $released,
            ]);
        }

        $ok = $this->create($branchId, $msisdn, $slotKey, $minutes, $slotLengthMinutes);
        Log::info('HoldService: hold result', [
            'slot_key' => $slotKey, 'ok' => $ok, 'msisdn_tail' => substr($msisdn, -4),
        ]);

        return $ok ? $slotKey : null;
    }

    public function create(
        int $branchId,
        string $msisdn,
        string $slotKey,
        int $minutes = 5,
        ?int $slotLengthMinutes = null
    ): bool {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $now = now($tz);
        $expires = $now->copy()->addMinutes($minutes);

        [$resDate, $resTime, $partyStr] = array_pad(explode('@', $slotKey, 4), 3, null);
        $resDate = (string) $resDate;
        $resTime = (string) $resTime;
        $party = (int) ($partyStr ?? 0);

        if ($resDate === '' || $resTime === '' || $party <= 0) {
            Log::warning('HoldService: refused malformed slot_key', ['slot_key' => $slotKey]);

            return false;
        }

        $resStart = Carbon::parse("$resDate $resTime", $tz);
        $resEnd = $slotLengthMinutes && $slotLengthMinutes > 0
            ? $resStart->copy()->addMinutes($slotLengthMinutes)
            : null;

        return DB::transaction(function () use ($branchId, $msisdn, $slotKey, $expires, $now, $resDate, $resTime, $party, $resStart, $resEnd) {
            DB::table('booking_holds')
                ->where('slot_key', $slotKey)
                ->where('expires_at', '<', $now)
                ->delete();

            $exists = DB::table('booking_holds')
                ->where('slot_key', $slotKey)
                ->where('expires_at', '>', $now)
                ->exists();

            if ($exists) {
                Log::info('HoldService: slot already held', ['slot_key' => $slotKey]);

                return false;
            }

            DB::table('booking_holds')->insert([
                'branch_id' => $branchId,
                'msisdn' => (string) $msisdn,
                'slot_key' => $slotKey,
                'res_date' => $resDate,
                'res_time' => $resTime,
                'party_size' => $party,
                'res_start' => $resStart?->toDateTimeString(),
                'res_end' => $resEnd?->toDateTimeString(),
                'expires_at' => $expires,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            Log::info('HoldService: slot held', [
                'slot_key' => $slotKey, 'expires_at' => $expires->toDateTimeString(),
            ]);

            return true;
        });
    }

    public function release(string $slotKey, ?string $msisdn = null): bool
    {
        $q = DB::table('booking_holds')->where('slot_key', $slotKey);
        if ($msisdn) {
            $q->where('msisdn', $msisdn);
        }

        $deleted = $q->delete();
        Log::info('HoldService: release', [
            'slot_key' => $slotKey, 'msisdn_tail' => $msisdn ? substr($msisdn, -4) : null, 'deleted' => $deleted,
        ]);

        return $deleted > 0;
    }

    public function releaseByPhone(string $msisdn): int
    {
        if ($msisdn === '') {
            return 0;
        }

        $deleted = DB::table('booking_holds')
            ->where('msisdn', $msisdn)
            ->delete();

        Log::info('HoldService: releaseByPhone', ['msisdn_tail' => substr($msisdn, -4), 'deleted' => $deleted]);

        return $deleted;
    }

    public function releaseByPhoneAndBranch(string $msisdn, int $branchId): int
    {
        if ($msisdn === '') {
            return 0;
        }

        $deleted = DB::table('booking_holds')
            ->where('msisdn', $msisdn)
            ->where('branch_id', $branchId)
            ->delete();

        Log::info('HoldService: releaseByPhoneAndBranch', [
            'msisdn_tail' => substr($msisdn, -4), 'branch' => $branchId, 'deleted' => $deleted,
        ]);

        return $deleted;
    }

    public function isValid(string $slotKey): bool
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        $exists = DB::table('booking_holds')
            ->where('slot_key', $slotKey)
            ->where('expires_at', '>', now($tz))
            ->exists();

        Log::info('HoldService: isValid', ['slot_key' => $slotKey, 'exists' => $exists]);

        return $exists;
    }

    public function isHeldBy(string $slotKey, string $msisdn): bool
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        $exists = DB::table('booking_holds')
            ->where('slot_key', $slotKey)
            ->where('msisdn', $msisdn)
            ->where('expires_at', '>', now($tz))
            ->exists();

        Log::info('HoldService: isHeldBy', [
            'slot_key' => $slotKey, 'msisdn_tail' => substr($msisdn, -4), 'exists' => $exists,
        ]);

        return $exists;
    }

    public function renew(string $slotKey, int $minutes = 5): bool
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        $updated = DB::table('booking_holds')
            ->where('slot_key', $slotKey)
            ->where('expires_at', '>', now($tz))
            ->update([
                'expires_at' => now($tz)->addMinutes($minutes),
                'updated_at' => now($tz),
            ]);

        Log::info('HoldService: renew', ['slot_key' => $slotKey, 'updated' => $updated > 0]);

        return $updated > 0;
    }

    public function findActive(string $slotKey): ?array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $row = DB::table('booking_holds')
            ->where('slot_key', $slotKey)
            ->where('expires_at', '>', now($tz))
            ->first();

        Log::info('HoldService: findActive', ['slot_key' => $slotKey, 'found' => (bool) $row]);

        return $row ? (array) $row : null;
    }

    public function cleanupExpired(): int
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        $deleted = DB::table('booking_holds')
            ->where('expires_at', '<', now($tz))
            ->delete();

        Log::info('HoldService: cleanupExpired', ['deleted' => $deleted]);

        return $deleted;
    }
}
