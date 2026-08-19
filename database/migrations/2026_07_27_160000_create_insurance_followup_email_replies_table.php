<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound insurer replies, as received.
 *
 * Kept separate from insurance_followup_emails because an insurer may answer a
 * statement more than once ("received", then "paid on the 15th"), and because a
 * reply that can't be matched to a statement must still be stored — an
 * unmatched reply is a work item, not something to throw away.
 *
 * The row holds what arrived; the human-assessed outcome stays on the statement
 * (reply_outcome / promised_payment_date), because deciding whether "we are
 * looking into it" is a payment promise is not a parser's job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_followup_email_replies', function (Blueprint $table) {
            $table->id();

            // Null while unmatched — the reply exists, we just don't know which
            // statement it answers yet.
            $table->unsignedBigInteger('followup_email_id')->nullable()->index();
            $table->unsignedBigInteger('insurer_id')->nullable()->index();

            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            // RFC Message-ID — the dedupe key, so re-running the import never
            // stores the same reply twice.
            $table->string('message_id')->nullable()->unique();
            $table->string('in_reply_to')->nullable();
            $table->timestamp('received_at')->nullable()->index();

            $table->longText('body_text')->nullable();

            // reference | thread | sender | manual — how we tied it to a statement.
            $table->string('matched_by', 16)->nullable();
            // unmatched | matched | applied (applied = an outcome was recorded from it)
            $table->string('status', 16)->default('unmatched')->index();

            // imap | folder | manual — which transport delivered it.
            $table->string('source', 16)->default('folder');
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_followup_email_replies');
    }
};
