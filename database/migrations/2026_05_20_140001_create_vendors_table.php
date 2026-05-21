<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // Display name (e.g. "Al-Salam Real Estate")
            $table->string('name', 191);

            // Optional short code (e.g. "LANDLORD-A"). Unique-when-not-null.
            $table->string('code', 32)->nullable()->unique();

            $table->string('contact_name', 191)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('address')->nullable();

            // Tax/commercial registration number for Kuwait compliance.
            $table->string('tax_number', 64)->nullable();

            // Default expense account to suggest when creating an Expense for this vendor.
            $table->unsignedBigInteger('default_account_id')->nullable();

            // Default payable account (typically 2010 Accounts Payable) for bill-style expenses.
            $table->unsignedBigInteger('default_payable_account_id')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('default_account_id')
                ->references('id')->on('chart_of_accounts')
                ->restrictOnDelete();
            $table->foreign('default_payable_account_id')
                ->references('id')->on('chart_of_accounts')
                ->restrictOnDelete();

            $table->index('name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
