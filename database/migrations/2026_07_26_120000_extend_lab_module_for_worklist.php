<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the lab module from a Filament-only relation manager into a real
 * two-sided workflow: doctor orders → lab assistant works the queue →
 * result report goes back to the doctor (and optionally to the patient).
 *
 * lab_orders gains:
 *   priority             — routine / urgent (drives worklist ordering)
 *   clinical_note        — why the doctor ordered it (lab reads this)
 *   lab_note             — the technician's / pathologist's comment
 *   sample_collected_by  — who drew the sample
 *   started_at / _by     — when analysis began
 *   completed_by         — who released the results
 *   reviewed_at/_by/note — the doctor acknowledging the report (the loop close)
 *   delivered_at/_channel/_by — report handed to the patient (whatsapp/print/download)
 *   cancelled_at/_by/_reason
 *
 * lab_order_items gains:
 *   result_numeric  — parsed numeric copy of result_value, so we can auto-flag
 *                     against a numeric reference range and trend a test later
 *   ref_low/ref_high— numeric bounds parsed off the range snapshot at order time
 *   visit_charge_id — the billing line this test created, so cancelling a test
 *                     can pull its charge back off the visit
 *   started_at, entered_by_user_id
 *
 * patient_files gains lab_order_id so a scanned/analyser-produced report (PDF
 * or image) reuses the whole PHI file pipeline (access logs, download route,
 * patient timeline) instead of getting a parallel attachments table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->string('priority', 16)->default('routine')->after('status')->index();

            $table->text('clinical_note')->nullable()->after('notes');
            $table->text('lab_note')->nullable()->after('clinical_note');

            $table->unsignedBigInteger('sample_collected_by_user_id')->nullable()->after('sample_collected_at');

            $table->dateTime('started_at')->nullable()->after('sample_collected_by_user_id');
            $table->unsignedBigInteger('started_by_user_id')->nullable()->after('started_at');

            $table->unsignedBigInteger('completed_by_user_id')->nullable()->after('completed_at');

            $table->dateTime('reviewed_at')->nullable()->after('completed_by_user_id');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable()->after('reviewed_at');
            $table->text('review_note')->nullable()->after('reviewed_by_user_id');

            $table->dateTime('delivered_at')->nullable()->after('review_note');
            $table->string('delivered_channel', 24)->nullable()->after('delivered_at');
            $table->unsignedBigInteger('delivered_by_user_id')->nullable()->after('delivered_channel');

            $table->dateTime('cancelled_at')->nullable()->after('delivered_by_user_id');
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable()->after('cancelled_at');
            $table->string('cancel_reason', 255)->nullable()->after('cancelled_by_user_id');

            // Worklist queries are always "open orders for my branch, oldest
            // first" or "completed today" — index the two shapes we sort on.
            $table->index(['status', 'ordered_at']);
            $table->index(['branch_id', 'status']);
        });

        Schema::table('lab_order_items', function (Blueprint $table) {
            $table->decimal('result_numeric', 14, 4)->nullable()->after('result_value');
            $table->decimal('ref_low', 14, 4)->nullable()->after('reference_range_snapshot');
            $table->decimal('ref_high', 14, 4)->nullable()->after('ref_low');

            $table->unsignedBigInteger('visit_charge_id')->nullable()->after('price_snapshot')->index();

            $table->dateTime('started_at')->nullable()->after('visit_charge_id');
            $table->unsignedBigInteger('entered_by_user_id')->nullable()->after('completed_by_user_id');
        });

        Schema::table('patient_files', function (Blueprint $table) {
            $table->unsignedBigInteger('lab_order_id')->nullable()->after('visit_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'ordered_at']);
            $table->dropIndex(['branch_id', 'status']);
            $table->dropColumn([
                'priority', 'clinical_note', 'lab_note',
                'sample_collected_by_user_id', 'started_at', 'started_by_user_id',
                'completed_by_user_id', 'reviewed_at', 'reviewed_by_user_id', 'review_note',
                'delivered_at', 'delivered_channel', 'delivered_by_user_id',
                'cancelled_at', 'cancelled_by_user_id', 'cancel_reason',
            ]);
        });

        Schema::table('lab_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'result_numeric', 'ref_low', 'ref_high',
                'visit_charge_id', 'started_at', 'entered_by_user_id',
            ]);
        });

        Schema::table('patient_files', function (Blueprint $table) {
            $table->dropColumn('lab_order_id');
        });
    }
};
