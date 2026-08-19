<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for follow-up emails sent to insurers from the collections board.
 *
 * One row per insurer per send (a bulk send of 40 claims across 5 insurers
 * writes 5 rows), holding exactly what left the building: the recipient, the
 * subject, the covering note, which claims were listed, and whether the mailer
 * accepted it. Failures are kept, not discarded — "we emailed them and it
 * bounced" is the answer the collections clerk actually needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_followup_emails', function (Blueprint $table) {
            $table->id();

            $table->foreignId('insurer_id')->constrained('insurers')->cascadeOnDelete();
            // The branch filter in force when the send was made, if any.
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->string('to_email');
            // Where it actually went, when a redirect is configured for
            // non-production environments. Null = delivered as addressed.
            $table->string('redirected_to')->nullable();
            $table->string('subject');
            $table->text('note')->nullable();

            // Snapshot of what was chased: ids + the human-readable numbers, so
            // the log stays readable even if a claim is later voided.
            $table->json('claim_ids');
            $table->json('claim_numbers');
            $table->unsignedInteger('claim_count')->default(0);
            $table->decimal('total_outstanding', 12, 3)->default(0);

            // sent | failed. Queued isn't used while the mailer runs sync, but
            // the column keeps the door open for a queued mailer later.
            $table->string('status', 16)->default('sent')->index();
            $table->text('error')->nullable();

            $table->unsignedBigInteger('sent_by_user_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable()->index();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['insurer_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_followup_emails');
    }
};
