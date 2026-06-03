<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalize HR from doctors-only to all staff (any User).
 *
 * Drops doctor_leaves + doctor_attendances (both verified empty at the
 * time of this migration — see clinic-active-initiatives memory for
 * provenance), recreates as staff_leaves + staff_attendances keyed on
 * user_id. doctor_id is kept as an optional convenience column so
 * doctor-scoped relation managers can filter cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Safety net — if either table has rows we abort rather than lose data.
        if (Schema::hasTable('doctor_attendances') && DB::table('doctor_attendances')->exists()) {
            throw new \RuntimeException('doctor_attendances has rows; migrate them to staff_attendances manually before running.');
        }
        if (Schema::hasTable('doctor_leaves') && DB::table('doctor_leaves')->exists()) {
            throw new \RuntimeException('doctor_leaves has rows; migrate them to staff_leaves manually before running.');
        }

        Schema::dropIfExists('doctor_attendances');
        Schema::dropIfExists('doctor_leaves');

        Schema::create('staff_leaves', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
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

            $table->index(['user_id', 'status']);
            $table->index(['doctor_id', 'status']);
            $table->index(['starts_on', 'ends_on']);
        });

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->date('work_date');
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->decimal('hours_worked', 6, 2)->default(0);

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'work_date'], 'staff_attendance_user_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('staff_leaves');

        // Recreate the old tables so a rollback isn't destructive.
        Schema::create('doctor_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->enum('type', ['annual', 'sick', 'maternity', 'unpaid', 'emergency', 'other'])->default('annual');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('days_count')->default(1);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending')->index();
            $table->text('decision_notes')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('doctor_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->date('work_date');
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->decimal('hours_worked', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['doctor_id', 'work_date'], 'doctor_attendance_doctor_date_unique');
        });
    }
};
