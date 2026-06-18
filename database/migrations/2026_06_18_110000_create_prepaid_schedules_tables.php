<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prepaid-expense amortization schedules. A prepayment (rent, insurance,
 * licences paid in advance) is capitalised into a prepaid asset (1160/1170)
 * and released to expense straight-line over its term: a monthly run posts
 * Dr Expense (e.g. 6210 Rent) / Cr Prepaid Asset. The prepaid_amortizations
 * table makes each month idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepaid_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->foreignId('prepaid_account_id')->constrained('chart_of_accounts')->cascadeOnUpdate();
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts')->cascadeOnUpdate();

            $table->decimal('total_amount', 14, 3);
            $table->unsignedInteger('term_months');
            $table->date('start_date');

            $table->string('status')->default('active'); // active / completed / cancelled
            $table->decimal('amortized_amount', 14, 3)->default(0);
            $table->date('last_amortized_on')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'branch_id']);
        });

        Schema::create('prepaid_amortizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prepaid_schedule_id')->constrained('prepaid_schedules')->cascadeOnDelete();
            $table->string('period_code', 7); // YYYY-MM
            $table->date('period_end');
            $table->decimal('amount', 14, 3);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['prepaid_schedule_id', 'period_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepaid_amortizations');
        Schema::dropIfExists('prepaid_schedules');
    }
};
