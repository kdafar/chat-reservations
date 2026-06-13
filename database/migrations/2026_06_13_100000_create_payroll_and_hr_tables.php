<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll & extended-HR module.
 *
 * Builds on the existing staff_leaves / staff_attendances generalization
 * (2026_05_30) and the doctor compensation ledger. Adds:
 *
 *   staff_compensation_profiles  per-user salary structure (basic + allowances
 *                                + recurring deductions), the non-doctor analog
 *                                of doctor_compensation_profiles. hire/termination
 *                                dates here drive end-of-service gratuity.
 *   payroll_runs / payslips      monthly payroll batch + one payslip per staff.
 *   payslip_lines                itemised earnings/deductions per payslip.
 *   staff_loans / repayments     loans & salary advances, repaid via payroll.
 *   staff_leave_entitlements     annual leave allowance per user/year (balance =
 *                                entitled + carried_over - used-approved-days).
 *   staff_settlements            end-of-service final settlement.
 *
 * All money is decimal(14,3) KWD (3 fils) to match the accounting module.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Per-user salary structure. One active profile per user; doctors may
        // also have one if they're on a base salary + commission arrangement.
        Schema::create('staff_compensation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->decimal('basic_salary', 14, 3)->default(0);
            // [{label, amount}] recurring monthly allowances (housing, transport…)
            $table->json('allowances')->nullable();
            // [{label, amount}] recurring monthly deductions (e.g. fixed penalties)
            $table->json('deductions')->nullable();
            $table->string('pay_currency', 3)->default('KWD');

            // Annual leave entitlement default used to seed yearly entitlements.
            $table->unsignedSmallInteger('annual_leave_days')->default(30);

            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
        });

        // Monthly payroll batch, scoped to a branch (nullable = all branches).
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month'); // 1-12

            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft')->index();

            $table->decimal('total_earnings', 14, 3)->default(0);
            $table->decimal('total_deductions', 14, 3)->default(0);
            $table->decimal('total_net', 14, 3)->default(0);
            // Slice of total used to size the GL entries.
            $table->decimal('total_salary', 14, 3)->default(0);     // basic+allowances-unpaid-other
            $table->decimal('total_commission', 14, 3)->default(0); // doctor cuts settled this run
            $table->decimal('total_loan_repaid', 14, 3)->default(0);

            $table->date('pay_date')->nullable();
            $table->text('notes')->nullable();

            // GL links: accrual (Dr 6015 / Cr 2030) and payment (Dr payables / Cr cash).
            $table->unsignedBigInteger('accrual_journal_entry_id')->nullable();
            $table->unsignedBigInteger('payment_journal_entry_id')->nullable();
            // Cash/bank account the net pay is disbursed from.
            $table->unsignedBigInteger('payment_account_id')->nullable();

            $table->unsignedBigInteger('prepared_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'period_year', 'period_month'], 'payroll_run_branch_period_unique');
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->decimal('basic_salary', 14, 3)->default(0);
            $table->decimal('allowances_total', 14, 3)->default(0);
            $table->decimal('commission_total', 14, 3)->default(0);
            $table->decimal('gross_pay', 14, 3)->default(0);

            $table->decimal('loan_deduction', 14, 3)->default(0);
            $table->decimal('unpaid_leave_deduction', 14, 3)->default(0);
            $table->decimal('other_deductions', 14, 3)->default(0);
            $table->decimal('deductions_total', 14, 3)->default(0);

            $table->decimal('net_pay', 14, 3)->default(0);

            // Days context used when computing unpaid-leave deduction.
            $table->unsignedSmallInteger('unpaid_leave_days')->default(0);
            $table->json('meta')->nullable(); // profile snapshot

            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');

            $table->timestamps();

            $table->unique(['payroll_run_id', 'user_id'], 'payslip_run_user_unique');
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', ['earning', 'deduction']);
            // basic | allowance | commission | loan | unpaid_leave | deduction | other
            $table->string('source', 30);
            $table->string('label');
            $table->decimal('amount', 14, 3)->default(0);
            $table->nullableMorphs('ref'); // e.g. ref_type=StaffLoan, ref_id=…
            $table->timestamps();
        });

        // Loans & salary advances. Repaid by withholding installments from payroll.
        Schema::create('staff_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->enum('type', ['loan', 'advance'])->default('loan');
            $table->decimal('principal_amount', 14, 3);
            $table->decimal('outstanding_amount', 14, 3);
            $table->decimal('installment_amount', 14, 3)->default(0); // monthly withholding

            $table->text('reason')->nullable();
            $table->date('issued_on');
            $table->enum('status', ['pending', 'active', 'settled', 'cancelled'])->default('pending')->index();

            // GL: disbursement (Dr 1130 Loans Receivable / Cr Cash).
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('payment_account_id')->nullable();

            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->dateTime('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        // One row per installment actually withheld via a payslip.
        Schema::create('staff_loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payslip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 3);
            $table->dateTime('settled_at')->nullable(); // set when the run is paid
            $table->timestamps();
        });

        // Annual leave allowance per user/year. used-days are computed from
        // approved staff_leaves, so balance = entitled + carried_over - used.
        Schema::create('staff_leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('leave_type', 20)->default('annual');
            $table->decimal('entitled_days', 6, 2)->default(0);
            $table->decimal('carried_over_days', 6, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'leave_type'], 'leave_entitlement_user_year_type_unique');
        });

        // End-of-service final settlement.
        Schema::create('staff_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->date('hire_date')->nullable();
            $table->date('last_working_day');
            $table->decimal('years_of_service', 6, 3)->default(0);
            $table->decimal('basic_salary_snapshot', 14, 3)->default(0);

            $table->decimal('gratuity_amount', 14, 3)->default(0);
            $table->decimal('leave_encashment', 14, 3)->default(0);
            $table->decimal('other_additions', 14, 3)->default(0);
            $table->decimal('loan_clawback', 14, 3)->default(0); // outstanding loans netted off
            $table->decimal('other_deductions', 14, 3)->default(0);
            $table->decimal('net_settlement', 14, 3)->default(0);

            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft')->index();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->unsignedBigInteger('accrual_journal_entry_id')->nullable();
            $table->unsignedBigInteger('payment_journal_entry_id')->nullable();
            $table->unsignedBigInteger('payment_account_id')->nullable();

            $table->unsignedBigInteger('prepared_by_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_settlements');
        Schema::dropIfExists('staff_leave_entitlements');
        Schema::dropIfExists('staff_loan_repayments');
        Schema::dropIfExists('staff_loans');
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('staff_compensation_profiles');
    }
};
