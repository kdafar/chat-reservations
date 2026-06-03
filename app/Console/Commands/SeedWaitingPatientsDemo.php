<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedWaitingPatientsDemo extends Command
{
    protected $signature = 'demo:waiting-patients
        {--doctor= : Doctor id to assign visits to (defaults to the first doctor with a user)}
        {--reset : Delete any "demo" visits/bookings/patients (msisdn starts with +96599DEMO) before seeding}';

    protected $description = 'Create 10–14 fake visits dated TODAY across waiting / in_progress / awaiting_stock so the v2 Waiting Patients page has something to render.';

    public function handle(): int
    {
        $doctor = $this->option('doctor')
            ? Doctor::find((int) $this->option('doctor'))
            : Doctor::whereNotNull('user_id')->whereNotNull('branch_id')->orderBy('id')->first();

        if (! $doctor) {
            $this->error('No doctor with a user + branch found. Run clinic:backfill-doctor-users first.');

            return self::FAILURE;
        }

        $this->info("Seeding demo queue for doctor #{$doctor->id} ({$doctor->name}) at branch #{$doctor->branch_id}.");

        if ($this->option('reset')) {
            $this->cleanup();
        }

        $partnerId = DB::table('branches')->where('id', $doctor->branch_id)->value('partner_id');

        $rows = [
            // [name, gender, status, minutes-waited, has_room]
            ['Maryam Al-Sabah',     'F', 'awaiting_doctor',  8,  3],
            ['Yousef Al-Khaled',    'M', 'awaiting_doctor', 17,  null],
            ['Noura Al-Rashid',     'F', 'in_progress',     22,  1],
            ['Ahmad Al-Otaibi',     'M', 'in_progress',      6,  2],
            ['Hessa Al-Awadhi',     'F', 'awaiting_stock',  38,  null],
            ['Salem Al-Mutawa',     'M', 'awaiting_doctor', 12,  null],
            ['Latifa Al-Saleh',     'F', 'awaiting_doctor',  4,  null],
            ['Khaled Al-Duwaila',   'M', 'in_progress',     14,  4],
            ['Reem Al-Failakawi',   'F', 'awaiting_stock',  26,  null],
            ['Fahad Al-Sabah',      'M', 'awaiting_doctor', 33,  null],
            ['Dalal Al-Sharhan',    'F', 'awaiting_doctor',  2,  null],
            ['Omar Al-Rashidi',     'M', 'in_progress',     41,  5],
        ];

        $created = 0;
        foreach ($rows as [$name, $gender, $status, $waitedMin, $roomId]) {
            DB::transaction(function () use ($doctor, $partnerId, $name, $gender, $status, $waitedMin, $roomId, &$created) {
                $msisdn = '+96599DEMO' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

                $patient = Patient::create([
                    'partner_id' => $partnerId,
                    'name' => $name,
                    'phone' => $msisdn,
                    'gender' => $gender === 'F' ? 'female' : 'male',
                    'dob' => now()->subYears(random_int(22, 56))->subDays(random_int(0, 365)),
                ]);

                $code = 'DEMO-' . strtoupper(Str::random(4));

                $booking = Booking::create([
                    'branch_id' => $doctor->branch_id,
                    'doctor_id' => $doctor->id,
                    'patient_id' => $patient->id,
                    'msisdn' => $msisdn,
                    'party_size' => 1,
                    'res_date' => now()->toDateString(),
                    'res_time' => now()->subMinutes($waitedMin + 5)->format('H:i:s'),
                    'status' => Booking::S_CONFIRMED,
                    'booking_code' => $code,
                    'source' => 'demo',
                    'checked_in_at' => now()->subMinutes($waitedMin + 3),
                ]);

                Visit::create([
                    'booking_id' => $booking->id,
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'branch_id' => $doctor->branch_id,
                    'restaurant_table_id' => $roomId,
                    'booking_code' => $code,
                    'source' => 'demo',
                    'status' => $status,
                    'checked_in_at' => now()->subMinutes($waitedMin + 3),
                    'queued_at' => now()->subMinutes($waitedMin),
                    'service_started_at' => $status === 'in_progress' ? now()->subMinutes($waitedMin) : null,
                    'notes' => "DEMO visit · {$status}",
                ]);

                $created++;
            });
        }

        $this->info("Created {$created} demo visits for today.");

        return self::SUCCESS;
    }

    protected function cleanup(): void
    {
        $patientIds = Patient::where('phone', 'like', '+96599DEMO%')->pluck('id');
        $visitIds = Visit::whereIn('patient_id', $patientIds)->pluck('id');
        $bookingIds = Booking::whereIn('patient_id', $patientIds)->pluck('id');

        Visit::whereIn('id', $visitIds)->forceDelete();
        Booking::whereIn('id', $bookingIds)->forceDelete();
        Patient::whereIn('id', $patientIds)->forceDelete();

        $this->line("Cleaned up {$visitIds->count()} visits, {$bookingIds->count()} bookings, {$patientIds->count()} patients.");
    }
}
