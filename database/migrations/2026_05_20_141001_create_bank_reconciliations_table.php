<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();

            // Auto-generated reference on first save (BR-YYYYMMDD-XXXX). Unique-when-not-null.
            $table->string('code', 32)->nullable()->unique();

            // The cash/bank account this reconciliation is scoped to (e.g. 1020, 1021, 1010-4).
            $table->unsignedBigInteger('account_id');

            $table->date('period_start');
            $table->date('period_end');

            // Statement-side balances — accountant enters these from the bank's PDF.
            $table->decimal('opening_balance', 14, 3)->default(0);
            $table->decimal('closing_balance', 14, 3)->default(0);

            // Book-side balances — calculated from journal entry lines hitting account_id.
            $table->decimal('book_opening_balance', 14, 3)->default(0);
            $table->decimal('book_closing_balance', 14, 3)->default(0);

            // in_progress — being worked on, lines can be edited / matched
            // completed   — frozen, only re-openable by admin
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');

            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('completed_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('account_id');
            $table->index('status');
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
