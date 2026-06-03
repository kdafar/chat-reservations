<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claim_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('claim_id')->constrained('insurance_claims');

            // Inherited from claim.branch_id at create time — used by BelongsToBranchScope
            // on InsuranceClaimPayment so finance staff only see payments for branches
            // they're assigned to.
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->decimal('amount', 12, 3);
            $table->dateTime('paid_at');

            $table->enum('method', ['cheque', 'transfer', 'cash'])
                ->default('transfer');

            // Bank wire ref / cheque number — paired with method for dedup.
            $table->string('reference_no', 100)->nullable();

            $table->unsignedBigInteger('received_by_user_id')->nullable();

            // FK to chart_of_accounts (the bank/cash account credited on receipt).
            $table->unsignedBigInteger('deposited_to_account_id')->nullable();

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('claim_id');

            // Dedup webhook/cheque entries on (method, reference_no), like visit_payments.
            // NULL reference_no is treated as distinct by MySQL/SQLite — cash rows coexist.
            $table->unique(['method', 'reference_no'], 'insurance_claim_payments_method_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_payments');
    }
};
