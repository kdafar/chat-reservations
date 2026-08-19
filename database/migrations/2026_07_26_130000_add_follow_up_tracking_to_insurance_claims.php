<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chase tracking for insurance claims.
 *
 * The claim's *status* says where it sits with the insurer; these columns say
 * what WE did about it — when we last chased, when to chase next, and what was
 * said. That is what the Insurance Follow-up board runs on. The full chase
 * history is appended to the existing `meta` JSON (meta.chases[]) so no extra
 * table is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->date('follow_up_at')->nullable()->after('paid_at');
            $table->dateTime('last_chased_at')->nullable()->after('follow_up_at');
            $table->unsignedInteger('chase_count')->default(0)->after('last_chased_at');
            $table->string('follow_up_note', 500)->nullable()->after('chase_count');

            // The board's default tab reads "open claims due for a chase".
            $table->index(['status', 'follow_up_at'], 'insurance_claims_followup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->dropIndex('insurance_claims_followup_idx');
            $table->dropColumn(['follow_up_at', 'last_chased_at', 'chase_count', 'follow_up_note']);
        });
    }
};
