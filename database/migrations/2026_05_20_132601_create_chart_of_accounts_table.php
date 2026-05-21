<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();

            // Numeric code (e.g. "1010", "4020"). Unique across the org.
            $table->string('code', 16)->unique();

            // Display name (e.g. "Cash - Main Branch")
            $table->string('name', 191);

            // Top-level classification. "contra_*" types are subtractive against
            // their parent class (e.g. Discount Given is contra_revenue).
            $table->enum('type', [
                'asset',
                'liability',
                'equity',
                'revenue',
                'cogs',
                'expense',
                'contra_asset',
                'contra_liability',
                'contra_revenue',
            ]);

            // Hierarchical: a parent group can roll up child accounts in reports.
            $table->unsignedBigInteger('parent_id')->nullable();

            // Optional branch scope (e.g. "Cash - Branch 4" is owned by branch 4).
            $table->unsignedBigInteger('branch_id')->nullable();

            // ISO 4217. KWD primary; future-proof for multi-currency.
            $table->string('currency', 3)->default('KWD');

            $table->boolean('is_active')->default(true);

            // System accounts (required for auto-postings) can't be deleted/renamed.
            $table->boolean('is_system')->default(false);

            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->index(['type', 'is_active']);
            $table->index('parent_id');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
