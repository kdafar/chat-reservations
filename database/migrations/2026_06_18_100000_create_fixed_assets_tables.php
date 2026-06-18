<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed-asset register + straight-line depreciation ledger.
 *
 * Each asset capitalises its cost into an asset account (e.g. 1210 Medical
 * Equipment) and depreciates straight-line over its useful life: a monthly run
 * posts Dr Depreciation Expense (6610) / Cr Accumulated Depreciation (e.g.
 * 1215). The fixed_asset_depreciations table makes each month idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable(); // medical_equipment / furniture / it / leasehold / software
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // GL wiring — which accounts this asset capitalises into / depreciates through.
            $table->foreignId('asset_account_id')->constrained('chart_of_accounts')->cascadeOnUpdate();
            $table->foreignId('accumulated_depreciation_account_id')->constrained('chart_of_accounts')->cascadeOnUpdate();
            $table->foreignId('depreciation_expense_account_id')->constrained('chart_of_accounts')->cascadeOnUpdate();

            $table->decimal('cost', 14, 3);
            $table->decimal('salvage_value', 14, 3)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->date('in_service_date');

            $table->string('method')->default('straight_line');
            $table->string('status')->default('active'); // active / fully_depreciated / disposed
            $table->decimal('accumulated_depreciation', 14, 3)->default(0);
            $table->date('last_depreciated_on')->nullable();

            // Disposal
            $table->date('disposed_on')->nullable();
            $table->decimal('disposal_proceeds', 14, 3)->nullable();
            $table->foreignId('disposal_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'branch_id']);
        });

        Schema::create('fixed_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('period_code', 7); // YYYY-MM
            $table->date('period_end');
            $table->decimal('amount', 14, 3);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
        Schema::dropIfExists('fixed_assets');
    }
};
