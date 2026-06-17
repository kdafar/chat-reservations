<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posting-account map: lets the accountant point each automated posting "role"
 * (cash, bank, AR, inventory, COGS, AP, revenue, payroll…) at a chart account.
 *
 * account_id NULL = use the built-in EVA default code (see PostingAccountMap::DEFAULTS).
 * Only a non-null account_id overrides the default in the posting engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posting_account_maps', function (Blueprint $table) {
            $table->id();
            $table->string('role', 64)->unique();
            $table->string('default_code', 16);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')->on('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_account_maps');
    }
};
