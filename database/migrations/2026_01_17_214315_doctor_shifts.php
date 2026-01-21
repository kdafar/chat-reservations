<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 1) Doctor capacity denominator (Utilization)
         * Table: doctor_shifts
         */
        if (! Schema::hasTable('doctor_shifts')) {
            Schema::create('doctor_shifts', function (Blueprint $table) {
                $table->id();

                // Assumes standard tables: doctors, branches
                $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();

                $table->date('shift_date');
                $table->time('start_time');
                $table->time('end_time');

                // Optional but useful for accuracy
                $table->unsignedInteger('break_minutes')->default(0);
                $table->boolean('is_cancelled')->default(false);

                $table->timestamps();

                $table->index(['doctor_id', 'shift_date']);
                $table->index(['branch_id', 'shift_date']);

                // Prevent exact duplicates
                $table->unique(['doctor_id', 'branch_id', 'shift_date', 'start_time', 'end_time'], 'doctor_shifts_unique');
            });
        }

        /**
         * 2) Split waiting vs service time
         * Add: visits.service_started_at
         */
        if (Schema::hasTable('visits') && ! Schema::hasColumn('visits', 'service_started_at')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dateTime('service_started_at')
                    ->nullable()
                    ->after('checked_in_at');

                $table->index('service_started_at');
            });
        }

        /**
         * 3) Structured cancellation/no-show reasons (reporting truth without touching status flow)
         * Add: bookings.cancelled_at, bookings.no_show_at, bookings.cancellation_reason_code, bookings.cancellation_comment
         * Optional: bookings.cancelled_by_user_id
         */
        if (Schema::hasTable('bookings')) {
            if (! Schema::hasColumn('bookings', 'cancelled_at')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dateTime('cancelled_at')->nullable()->after('status');
                    $table->index('cancelled_at');
                });
            }

            if (! Schema::hasColumn('bookings', 'no_show_at')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dateTime('no_show_at')->nullable()->after('cancelled_at');
                    $table->index('no_show_at');
                });
            }

            if (! Schema::hasColumn('bookings', 'cancellation_reason_code')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->string('cancellation_reason_code', 64)->nullable()->after('no_show_at');
                    $table->index('cancellation_reason_code');
                });
            }

            if (! Schema::hasColumn('bookings', 'cancellation_comment')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->text('cancellation_comment')->nullable()->after('cancellation_reason_code');
                });
            }

            // Optional attribution to user (safe + nullable)
            if (! Schema::hasColumn('bookings', 'cancelled_by_user_id')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->foreignId('cancelled_by_user_id')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete()
                        ->after('cancellation_comment');

                    $table->index('cancelled_by_user_id');
                });
            }
        }
    }

    public function down(): void
    {
        // Reverse bookings columns (drop FKs safely first)
        if (Schema::hasTable('bookings')) {
            if (Schema::hasColumn('bookings', 'cancelled_by_user_id')) {
                Schema::table('bookings', function (Blueprint $table) {
                    // Drop FK if it exists (Laravel will infer name in most cases)
                    try {
                        $table->dropConstrainedForeignId('cancelled_by_user_id');
                    } catch (\Throwable $e) {
                        // Fallback if FK name differs
                        try {
                            $table->dropForeign(['cancelled_by_user_id']);
                        } catch (\Throwable $e2) {
                        }
                        try {
                            $table->dropColumn('cancelled_by_user_id');
                        } catch (\Throwable $e3) {
                        }
                    }
                });
            }

            foreach (['cancellation_comment', 'cancellation_reason_code', 'no_show_at', 'cancelled_at'] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    Schema::table('bookings', function (Blueprint $table) use ($col) {
                        try {
                            $table->dropIndex([$col]);
                        } catch (\Throwable $e) {
                        }
                        try {
                            $table->dropColumn($col);
                        } catch (\Throwable $e2) {
                        }
                    });
                }
            }
        }

        // Reverse visits column
        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'service_started_at')) {
            Schema::table('visits', function (Blueprint $table) {
                try {
                    $table->dropIndex(['service_started_at']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('service_started_at');
            });
        }

        // Drop doctor_shifts table
        if (Schema::hasTable('doctor_shifts')) {
            Schema::drop('doctor_shifts');
        }
    }
};
