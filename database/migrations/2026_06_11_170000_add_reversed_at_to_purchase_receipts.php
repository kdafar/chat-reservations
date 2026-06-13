<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a goods receipt be reversed (receiving error): the stock is consumed back
 * out, the GL entry reversed, and the receipt flagged so it no longer counts
 * toward received value / Accounts Payable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dateTime('reversed_at')->nullable()->after('journal_entry_id');
            $table->unsignedBigInteger('reversed_by_user_id')->nullable()->after('reversed_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table) {
            $table->dropColumn(['reversed_at', 'reversed_by_user_id']);
        });
    }
};
