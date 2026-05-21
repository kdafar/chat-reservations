<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillDoctorUsers extends Command
{
    protected $signature = 'clinic:backfill-doctor-users {--dry-run : Show what would happen without writing}';

    protected $description = 'Create auth User accounts (role: clinic_doctor) for doctors that lack one.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $doctors = Doctor::whereNull('user_id')->get();

        // Also fix doctors that DO have a user but are missing the
        // branch_user pivot row for THEIR SPECIFIC branch — same root cause,
        // different symptom. (Just "any row" isn't enough — a doctor user
        // may have a row for an unrelated branch.)
        $missingPivot = Doctor::query()
            ->whereNotNull('user_id')
            ->whereNotNull('branch_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('branch_user')
                    ->whereColumn('branch_user.user_id', 'doctors.user_id')
                    ->whereColumn('branch_user.branch_id', 'doctors.branch_id');
            })
            ->get();

        if ($missingPivot->isNotEmpty()) {
            $this->info("Found {$missingPivot->count()} doctor(s) missing a branch_user pivot row.".($dry ? ' [DRY RUN]' : ''));
            foreach ($missingPivot as $doctor) {
                $this->line("  → doctor #{$doctor->id} ({$doctor->name}) — user {$doctor->user_id} ↔ branch {$doctor->branch_id}");
                if (! $dry) {
                    DB::table('branch_user')->updateOrInsert(
                        ['user_id' => $doctor->user_id, 'branch_id' => $doctor->branch_id],
                        [],
                    );
                }
            }
        }

        if ($doctors->isEmpty()) {
            if ($missingPivot->isEmpty()) {
                $this->info('No doctors without a linked user. Nothing to do.');
            }

            return self::SUCCESS;
        }

        $this->info("Found {$doctors->count()} doctor(s) without a linked user.".($dry ? ' [DRY RUN]' : ''));

        $rows = [];

        foreach ($doctors as $doctor) {
            $email = $doctor->email ?: 'doctor-'.$doctor->id.'@clinic.local';
            $existing = User::where('email', $email)->first();

            $password = null;
            $action = null;
            $userId = null;

            if ($existing) {
                $action = 'linked existing';
                $userId = $existing->id;

                if (! $dry) {
                    DB::transaction(function () use ($existing, $doctor) {
                        if (! $existing->hasRole('clinic_doctor')) {
                            $existing->assignRole('clinic_doctor');
                        }
                        $doctor->update(['user_id' => $existing->id]);
                    });
                }
            } else {
                $action = 'created new';
                $password = Str::password(12, symbols: false);

                if (! $dry) {
                    DB::transaction(function () use ($doctor, $email, $password, &$userId) {
                        $user = User::create([
                            'name' => $doctor->name ?: 'Doctor #'.$doctor->id,
                            'email' => $email,
                            'phone' => $doctor->phone,
                            'password' => $password,
                            'status' => 'active',
                            'email_verified_at' => now(),
                        ]);
                        $user->assignRole('clinic_doctor');
                        $doctor->update(['user_id' => $user->id]);
                        $userId = $user->id;
                    });
                }
            }

            $rows[] = [
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->name,
                'email' => $email,
                'user_id' => $userId ?? '-',
                'action' => $action,
                'password' => $password ?: '(existing)',
            ];
        }

        $this->table(
            ['Doctor ID', 'Doctor Name', 'Email', 'User ID', 'Action', 'Password'],
            $rows,
        );

        if ($dry) {
            $this->warn('Dry run — no changes were made. Re-run without --dry-run to apply.');
        } else {
            $this->info('Done. Copy any generated passwords above — they are not stored anywhere else.');
        }

        return self::SUCCESS;
    }
}
