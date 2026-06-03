<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two-column audit trail for the "Option 2 discharge gate":
     *   - if the patient has an active insurance policy, reception must
     *     either file a claim OR explicitly skip before discharge.
     *
     * `insurance_claim_skipped_at` records WHEN reception chose to skip
     * (NULL means "no decision yet" or "claim filed via InsuranceClaim row").
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->timestamp('insurance_claim_skipped_at')->nullable()->after('completed_at');
            $table->unsignedBigInteger('insurance_claim_skipped_by_user_id')->nullable()->after('insurance_claim_skipped_at');
            $table->string('insurance_claim_skip_reason', 500)->nullable()->after('insurance_claim_skipped_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['insurance_claim_skipped_at', 'insurance_claim_skipped_by_user_id', 'insurance_claim_skip_reason']);
        });
    }
};
