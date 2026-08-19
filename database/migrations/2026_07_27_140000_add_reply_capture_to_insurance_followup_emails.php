<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The other half of a follow-up: what the insurer said back.
 *
 * The send log recorded only our side, so the board could show "chased 3 times"
 * without anyone being able to answer "and what did they say?". Replies are
 * recorded against the send they answer, which is also what carries the claim
 * list — so one reply resolves every claim that statement covered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_followup_emails', function (Blueprint $table) {
            // When the insurer answered (their date, not ours — a reply logged
            // on Sunday may have arrived Thursday).
            $table->timestamp('replied_at')->nullable()->after('sent_at');
            // payment_promised | documents_required | rejected | partial | no_response | other
            $table->string('reply_outcome', 32)->nullable()->after('replied_at')->index();
            $table->text('reply_note')->nullable()->after('reply_outcome');
            // What they committed to, when they committed to anything.
            $table->date('promised_payment_date')->nullable()->after('reply_note');
            $table->decimal('promised_amount', 12, 3)->nullable()->after('promised_payment_date');

            $table->unsignedBigInteger('reply_recorded_by_user_id')->nullable()->after('promised_amount');
            $table->timestamp('reply_recorded_at')->nullable()->after('reply_recorded_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_followup_emails', function (Blueprint $table) {
            $table->dropColumn([
                'replied_at', 'reply_outcome', 'reply_note',
                'promised_payment_date', 'promised_amount',
                'reply_recorded_by_user_id', 'reply_recorded_at',
            ]);
        });
    }
};
