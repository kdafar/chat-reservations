<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('visit_id')->constrained('visits');
            $table->foreignId('patient_policy_id')
                ->constrained('patient_insurance_policies');

            // Preauth that authorized this claim, when applicable.
            $table->foreignId('preauth_id')
                ->nullable()
                ->constrained('insurance_preauthorizations');

            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // Internal claim number (e.g. CLM-YYYYMMDD-XXXXX). Unique.
            $table->string('claim_number', 50)->unique();

            $table->unsignedBigInteger('submitted_by_user_id')->nullable();
            $table->dateTime('submitted_at')->nullable();

            // Sum of visit charges in scope of this claim.
            $table->decimal('total_charged', 12, 3)->default(0);

            $table->decimal('patient_copay', 12, 3)->default(0);

            // What we billed the insurer (total_charged - patient_copay).
            $table->decimal('insurer_payable', 12, 3)->default(0);

            $table->decimal('approved_amount', 12, 3)->default(0);
            $table->decimal('rejected_amount', 12, 3)->default(0);
            $table->decimal('paid_amount', 12, 3)->default(0);

            // Bad-debt write-off portion (insurer_payable - approved - rejected at close).
            $table->decimal('write_off_amount', 12, 3)->default(0);

            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'approved',
                'partially_approved',
                'rejected',
                'paid',
                'void',
            ])->default('draft')->index();

            $table->text('decision_notes')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('paid_at')->nullable();

            // Path to the uploaded EOB / remittance advice document.
            $table->string('eob_path', 500)->nullable();

            // Insurer's claim id / reference number.
            $table->string('reference_no', 100)->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['visit_id', 'status']);
            $table->index('patient_policy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
    }
};
