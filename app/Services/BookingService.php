<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchAvailabilityRule;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\WhatsappContact;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BookingService
{
    /**
     * Confirm (or update) a booking from a hold.
     *
     * Single entry point for:
     * - WhatsApp flow (current)
     * - Web (later)
     * - Admin (later)
     *
     * Must ALWAYS set res_start/res_end for correct slot blocking.
     */
    public function confirmFromHold(array $hold, array $attrs): Booking
    {
        $tz = config('app.timezone', 'Asia/Kuwait');

        $branchId = (int) ($attrs['branch_id'] ?? $hold['branch_id'] ?? 0);
        if ($branchId <= 0) {
            throw new \InvalidArgumentException('branch_id is required.');
        }

        /** @var Branch|null $branch */
        $branch = Branch::find($branchId);
        if (! $branch) {
            throw new \InvalidArgumentException('Invalid branch_id.');
        }

        // Date + Time are required for booking time window
        $resDateRaw = $attrs['res_date'] ?? $hold['res_date'] ?? null;
        $resTimeRaw = $attrs['res_time'] ?? $hold['res_time'] ?? null;

        if (! $resDateRaw || ! $resTimeRaw) {
            throw new \InvalidArgumentException('res_date and res_time are required.');
        }

        $resDate = $this->normalizeDateString($resDateRaw);
        $resTime = $this->normalizeTimeString($resTimeRaw);

        // Build res_start and res_end based on BranchAvailabilityRule
        $resStart = Carbon::parse("{$resDate} {$resTime}", $tz)->seconds(0);
        $resEnd = $this->calculateSlotEnd($resStart, $branchId);

        $msisdn = (string) ($attrs['msisdn'] ?? $hold['msisdn'] ?? '');
        $msisdn = trim($msisdn);
        $msisdnDigits = $msisdn !== '' ? preg_replace('/\D+/', '', $msisdn) : '';
        $msisdnFinal = $msisdnDigits ?: $msisdn;

        $source = (string) ($attrs['source'] ?? $hold['source'] ?? '');
        $source = $this->normalizeSource($source) ?: 'whatsapp';

        $sourceRef = $attrs['source_ref'] ?? $hold['source_ref'] ?? null;
        $sourceRef = $sourceRef !== null ? (string) $sourceRef : null;

        $existingId = (int) ($attrs['existing_booking_id'] ?? 0);

        return DB::transaction(function () use (
            $existingId,
            $attrs,
            $hold,
            $branch,
            $branchId,
            $resDate,
            $resTime,
            $resStart,
            $resEnd,
            $msisdnFinal,
            $source,
            $sourceRef
        ): Booking {
            // 1) Keep WhatsApp contact snapshot behavior (safe)
            $contact = null;
            if ($msisdnFinal !== '') {
                $contact = WhatsappContact::updateOrCreate(
                    ['msisdn' => $msisdnFinal],
                    [
                        'name' => (string) ($attrs['name'] ?? ''),
                        'email' => (string) ($attrs['email'] ?? ''),
                        'locale' => (string) ($attrs['locale'] ?? app()->getLocale()),
                        'last_seen_at' => now(),
                        'opt_in' => (bool) ($attrs['agree_terms'] ?? false),
                    ]
                );
            }

            // 2) Prepare core fields (do NOT include anything you don’t have in DB)
            $doctorId = isset($attrs['doctor_id']) && (int) $attrs['doctor_id'] > 0 ? (int) $attrs['doctor_id'] : null;
            $tableId = isset($attrs['table_id']) && (int) $attrs['table_id'] > 0 ? (int) $attrs['table_id'] : null;

            // Auto-room assignment from doctor (only if table_id empty)
            if (! $tableId && $doctorId) {
                $docRoomId = Doctor::whereKey($doctorId)->value('restaurant_table_id');
                if ($docRoomId) {
                    $tableId = (int) $docRoomId;
                }
            }

            // patient_id resolution:
            // - Respect explicit attrs['patient_id'] if present
            // - Else attempt resolve by phone+partner (if schema supports it)
            $patientId = isset($attrs['patient_id']) && (int) $attrs['patient_id'] > 0 ? (int) $attrs['patient_id'] : null;
            if (! $patientId && $msisdnFinal !== '') {
                $patientId = $this->resolveOrCreatePatientId($msisdnFinal, $branch, $attrs);
            }

            // 2.1) PATCH: Server-side slot conflict check (doctor mode)
            // Prevent double booking due to race conditions / parallel confirms.
            if ($doctorId) {
                $this->assertDoctorSlotAvailable(
                    doctorId: $doctorId,
                    resStart: $resStart,
                    resEnd: $resEnd,
                    ignoreBookingId: $existingId > 0 ? $existingId : null
                );
            }

            $payload = [
                'branch_id' => $branchId,
                'doctor_id' => $doctorId,
                'table_id' => $tableId,
                'patient_id' => $patientId,
                'contact_id' => $contact?->id,
                'msisdn' => $msisdnFinal,

                // Keep your system’s “pax” default
                'party_size' => (int) ($attrs['party_size'] ?? $hold['party_size'] ?? 1),

                'res_date' => $resDate,
                'res_time' => $resTime,
                'res_start' => $resStart,
                'res_end' => $resEnd,

                // Status: default confirmed (aligns with current WA behavior)
                'status' => (string) ($attrs['status'] ?? (defined(Booking::class.'::S_CONFIRMED') ? Booking::S_CONFIRMED : 'confirmed')),

                'notes' => (string) ($attrs['notes'] ?? ''),
            ];

            // Add-only fields (nullable columns in your spec)
            if (Schema::hasColumn('bookings', 'source')) {
                $payload['source'] = $source;
            }
            if (Schema::hasColumn('bookings', 'source_ref')) {
                $payload['source_ref'] = $sourceRef;
            }

            // Meta merge: never wipe existing meta
            $metaPatch = [
                'slot_key' => (string) ($hold['slot_key'] ?? ''),
                'contact_snapshot' => [
                    'name' => (string) ($attrs['name'] ?? ''),
                    'phone' => (string) ($attrs['phone'] ?? $msisdnFinal),
                    'email' => (string) ($attrs['email'] ?? ''),
                    'agree_terms' => (bool) ($attrs['agree_terms'] ?? false),
                    'source' => $source,
                ],
            ];

            // 3) Update existing booking OR create new
            if ($existingId > 0) {
                /** @var Booking|null $b */
                $b = Booking::lockForUpdate()->find($existingId);

                if ($b) {
                    // Conservative ownership check (don’t hijack other booking)
                    if ($b->msisdn && $msisdnFinal && $b->msisdn !== $msisdnFinal) {
                        $b = null; // force create new below
                    } else {
                        $b->fill($payload);

                        // Keep existing booking_code if present; else generate
                        if (empty($b->booking_code)) {
                            $b->booking_code = $this->uniqueBookingCode();
                        }

                        // Only set source/source_ref if empty (do not override)
                        if (Schema::hasColumn('bookings', 'source') && empty($b->source)) {
                            $b->source = $source;
                        }
                        if (Schema::hasColumn('bookings', 'source_ref') && empty($b->source_ref) && $sourceRef) {
                            $b->source_ref = $sourceRef;
                        }

                        $prevMeta = is_array($b->meta) ? $b->meta : [];
                        $b->meta = array_replace_recursive($prevMeta, $metaPatch);

                        $b->save();

                        $this->ensureQrToken($b);

                        return $b->refresh();
                    }
                }
            }

            // Create new booking
            $b = new Booking($payload);

            if (empty($b->booking_code)) {
                $b->booking_code = $this->uniqueBookingCode();
            }

            $b->meta = array_replace_recursive(is_array($b->meta) ? $b->meta : [], $metaPatch);

            $b->save();

            $this->ensureQrToken($b);

            return $b->refresh();
        });
    }

    /**
     * PATCH: Doctor slot conflict guard.
     * Throws RuntimeException('SLOT_TAKEN') if overlap exists.
     */
    protected function assertDoctorSlotAvailable(int $doctorId, Carbon $resStart, Carbon $resEnd, ?int $ignoreBookingId = null): void
    {
        $blockingStatuses = ['confirmed', 'pending'];

        $q = Booking::query()
            ->where('doctor_id', $doctorId)
            ->whereIn('status', $blockingStatuses)
            ->where(function ($q) use ($resStart, $resEnd) {
                // overlap: A.start < B.end AND A.end > B.start
                $q->where('res_start', '<', $resEnd)
                    ->where('res_end', '>', $resStart);
            });

        if ($ignoreBookingId) {
            $q->where('id', '!=', $ignoreBookingId);
        }

        if ($q->exists()) {
            throw new \RuntimeException('SLOT_TAKEN');
        }
    }

    /**
     * Slot end calculation aligned with your BookingResource/AvailabilityService day mapping.
     */
    protected function calculateSlotEnd(Carbon $start, int $branchId): Carbon
    {
        // IMPORTANT: keep same convention as AvailabilityService:
        // dayOfWeekIso: 1..7 (Mon..Sun) => map Sun(7) to 0, keep Mon..Sat as 1..6
        $dowIso = (int) $start->dayOfWeekIso;        // 1..7
        $dowZeroBased = $dowIso === 7 ? 0 : $dowIso; // 0..6 (Sun=0)

        $rule = BranchAvailabilityRule::query()
            ->where('branch_id', $branchId)
            ->where('day_of_week', $dowZeroBased)
            ->first();

        $minutes = (int) (
            $rule?->slot_length_minutes
            ?? $rule?->slot_step_minutes
            ?? config('booking.slot_interval', 30)
        );

        $minutes = max(5, $minutes);

        return $start->copy()->addMinutes($minutes)->seconds(0);
    }

    protected function normalizeDateString($date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        $s = trim((string) $date);

        // Filament date picker usually gives YYYY-MM-DD
        return substr($s, 0, 10);
    }

    protected function normalizeTimeString($time): string
    {
        if ($time instanceof \DateTimeInterface) {
            return $time->format('H:i:00');
        }

        $s = trim((string) $time);

        // Accept "HH:MM" or "HH:MM:SS"
        if (preg_match('/^\d{2}:\d{2}$/', $s)) {
            return $s.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $s)) {
            return $s;
        }

        throw new \InvalidArgumentException('Invalid res_time format.');
    }

    protected function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));
        if ($source === '') {
            return '';
        }

        return match ($source) {
            'wa', 'wa_flow' => 'whatsapp',
            default => $source,
        };
    }

    protected function uniqueBookingCode(): string
    {
        // booking_code is GLOBALLY unique. withoutGlobalScopes() so the
        // uniqueness check spans every clinic/branch — under BelongsToBranchScope
        // a non-admin would only check their own branch and could approve a code
        // already used elsewhere, tripping the unique index on insert.
        do {
            $code = strtoupper(Str::random(6));
        } while (Booking::withoutGlobalScopes()->where('booking_code', $code)->exists());

        return $code;
    }

    /**
     * Uses your existing QrPassService. Safe: no-op if token already exists.
     */
    protected function ensureQrToken(Booking $booking): void
    {
        if (! Schema::hasColumn('bookings', 'qr_token')) {
            return;
        }

        try {
            app(\App\Services\QrPassService::class)->ensureToken($booking);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Patient resolution (conservative, partner-scoped when possible).
     */
    protected function resolveOrCreatePatientId(string $phone, Branch $branch, array $attrs): ?int
    {
        if (! class_exists(Patient::class)) {
            return null;
        }

        $q = Patient::query()->where('phone', $phone);

        // Partner scoping when available
        if (Schema::hasColumn('patients', 'partner_id') && ! empty($branch->partner_id)) {
            $q->where('partner_id', $branch->partner_id);
        }

        $existing = $q->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $name = (string) ($attrs['name'] ?? '');
        $name = trim($name) !== '' ? trim($name) : $phone;

        $data = [
            'name' => $name,
            'phone' => $phone,
        ];

        if (Schema::hasColumn('patients', 'partner_id') && ! empty($branch->partner_id)) {
            $data['partner_id'] = $branch->partner_id;
        }

        try {
            $p = Patient::create($data);

            return (int) $p->id;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
