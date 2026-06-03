<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_preauthorizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_policy_id')
                ->constrained('patient_insurance_policies');

            // Optional — preauths may be requested before a visit row exists.
            $table->foreignId('visit_id')
                ->nullable()
                ->constrained('visits');

            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->unsignedBigInteger('requested_by_user_id')->nullable();

            // Array of requested services with estimated amounts:
            // [{label, estimated_amount}]
            $table->json('services');

            $table->decimal('estimated_total', 12, 3)->default(0);

            $table->dateTime('requested_at');

            // Insurer's reference number for this preauth request.
            $table->string('reference_no', 100)->nullable();

            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'approved',
                'partially_approved',
                'rejected',
                'expired',
            ])->default('draft')->index();

            $table->decimal('approved_amount', 12, 3)->nullable();

            // Path to the uploaded insurer approval letter (PDF/image).
            $table->string('approval_letter_path', 500)->nullable();

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->text('decision_notes')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_preauthorizations');
    }
};
