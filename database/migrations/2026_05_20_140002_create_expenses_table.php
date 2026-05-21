<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // Auto-generated reference on first save (EXP-YYYYMMDD-XXXX). Unique-when-not-null.
            $table->string('code', 32)->nullable()->unique();

            $table->date('expense_date');

            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();

            // The expense account being debited (e.g. 6030 Rent).
            $table->unsignedBigInteger('account_id');

            // When set, the expense is paid immediately (Dr Expense / Cr Cash or Bank).
            // When null, the expense is billed and accrues to Accounts Payable.
            $table->unsignedBigInteger('payment_account_id')->nullable();

            // KWD precision throughout the system (decimal:3).
            $table->decimal('amount', 14, 3);

            $table->string('description', 500)->nullable();
            $table->string('reference_no', 191)->nullable();

            // Receipt scan/image stored on disk-public.
            $table->string('receipt_path')->nullable();

            // draft  — created but not yet posted to the GL
            // posted — journal entry posted, immutable
            // void   — original posted entry has been reversed
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');

            $table->dateTime('posted_at')->nullable();
            $table->unsignedBigInteger('posted_by_user_id')->nullable();

            // FK back to the journal_entry created when this expense is posted.
            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('payment_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('posted_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->index('expense_date');
            $table->index('status');
            $table->index('vendor_id');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
