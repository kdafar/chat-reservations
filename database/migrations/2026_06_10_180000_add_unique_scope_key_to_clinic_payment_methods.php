<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One payment-method `key` per (partner, branch) scope. Note: MySQL treats NULLs
 * as distinct in a unique index, so this enforces branch-level rows (both ids
 * set) reliably; the global/clinic NULL-scope rows are guarded in the model's
 * saving() hook (which is null-safe). Belt and braces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_payment_methods', function (Blueprint $table) {
            $table->unique(['partner_id', 'branch_id', 'key'], 'cpm_scope_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_payment_methods', function (Blueprint $table) {
            $table->dropUnique('cpm_scope_key_unique');
        });
    }
};
