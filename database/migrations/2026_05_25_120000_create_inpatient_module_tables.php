<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. WARDS — physical groupings of beds (General, ICU, Pediatric, etc.)
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            $table->string('name');
            $table->string('code')->nullable();
            // general/icu/pediatric/maternity/isolation/vip
            $table->string('ward_type')->default('general');

            // Default daily rate; individual beds can override.
            $table->decimal('daily_rate', 10, 3)->default(0);

            // any/male/female — used at bed assignment time
            $table->string('gender_restriction')->default('any');

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });

        // 2. BEDS — individual beds inside a ward.
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            // Denormalized for fast branch-scoped queries (board view).
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            $table->string('code'); // "A-101"
            // available / occupied / reserved / maintenance / cleaning
            $table->string('status')->default('available');

            // null = falls back to wards.daily_rate
            $table->decimal('daily_rate_override', 10, 3)->nullable();

            // ["oxygen", "ventilator", "isolation"]
            $table->json('features')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ward_id', 'code']);
            $table->index(['branch_id', 'status']);
        });

        // 3. ADMISSIONS — the inpatient stay record.
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('admitting_doctor_id')->constrained('doctors')->restrictOnDelete();

            // The OPD/ER visit that triggered admission (nullable for direct admits).
            $table->foreignId('admitting_visit_id')->nullable()->constrained('visits')->nullOnDelete();
            // The Visit created at discharge to bundle final billing (bed days + items).
            $table->foreignId('final_visit_id')->nullable()->constrained('visits')->nullOnDelete();

            $table->string('admission_code')->unique(); // ADM-2026-0001
            $table->timestamp('admitted_at');
            $table->timestamp('discharged_at')->nullable();
            $table->timestamp('expected_discharge_at')->nullable();

            $table->text('admission_reason');
            $table->text('diagnosis')->nullable();

            // active / discharged / transferred_out / lama / expired
            $table->string('status')->default('active');

            $table->foreignId('discharged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('discharge_summary')->nullable();

            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['patient_id', 'status']);
            $table->index('admitted_at');
        });

        // 4. ADMISSION_BED_STAYS — bed-assignment history. A transfer ends one
        // stay and opens another; this lets bills reflect mixed rates and
        // gives nursing a clean audit trail.
        Schema::create('admission_bed_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('bed_id')->constrained('beds')->restrictOnDelete();
            // Denormalized for reports (ward occupancy / revenue by ward).
            $table->foreignId('ward_id')->constrained('wards')->restrictOnDelete();

            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            // SNAPSHOT of the daily rate at the time of assignment — rate
            // changes later don't retro-bill.
            $table->decimal('daily_rate', 10, 3);

            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason_for_change')->nullable(); // "Transferred to ICU"

            $table->timestamps();

            $table->index(['admission_id', 'released_at']);
            $table->index(['bed_id', 'released_at']);
        });

        // 5. ADMISSION_CHARGES — per-day bed charges generated by the nightly
        // cron. Immutable history; the discharge bill sums these.
        Schema::create('admission_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('bed_stay_id')->nullable()->constrained('admission_bed_stays')->nullOnDelete();

            $table->date('charge_date');
            $table->decimal('amount', 10, 3);
            $table->string('description');
            // bed_day / manual
            $table->string('source')->default('bed_day');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Stops the cron from double-billing a day if it runs twice.
            $table->unique(['admission_id', 'charge_date', 'source'], 'admission_charges_day_uq');
            $table->index('charge_date');
        });

        // 6. ADMISSION_ROUNDS — daily progress notes by the attending /
        // consulting doctor(s). One row per (admission, doctor, day).
        Schema::create('admission_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();

            $table->date('round_date');
            $table->json('vitals')->nullable();      // { "bp": "120/80", "temp": 37.1, ... }
            $table->text('progress_notes')->nullable();
            $table->text('med_changes')->nullable();
            $table->text('next_steps')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['admission_id', 'doctor_id', 'round_date'], 'admission_rounds_unique_day');
            $table->index(['admission_id', 'round_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_rounds');
        Schema::dropIfExists('admission_charges');
        Schema::dropIfExists('admission_bed_stays');
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('wards');
    }
};
