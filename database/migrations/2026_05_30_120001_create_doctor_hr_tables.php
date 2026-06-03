<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doctor HR: leaves (annual/sick/etc with approval workflow) +
 * attendance (clock-in/clock-out with computed hours_worked).
 *
 * Both tables intentionally minimal — they cover the customer's
 * "doctors & HR" feature row without dragging in a full payroll
 * module (the existing DoctorCompensationLedger already does payroll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_leaves', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->enum('type', ['annual', 'sick', 'maternity', 'unpaid', 'emergency', 'other'])
                ->default('annual');

            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('days_count')->default(1);

            $table->text('reason')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending')
                ->index();

            $table->text('decision_notes')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();

            $table->unsignedBigInteger('requested_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['doctor_id', 'status']);
            $table->index(['starts_on', 'ends_on']);
        });

        Schema::create('doctor_attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // One row per doctor per work-date. Clock-out can be null
            // while the doctor is still on shift.
            $table->date('work_date');
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();

            // Cached on save() so reports don't recompute on every read.
            $table->decimal('hours_worked', 6, 2)->default(0);

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // A doctor can only have one attendance row per work-date.
            $table->unique(['doctor_id', 'work_date'], 'doctor_attendance_doctor_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_attendances');
        Schema::dropIfExists('doctor_leaves');
    }
};
