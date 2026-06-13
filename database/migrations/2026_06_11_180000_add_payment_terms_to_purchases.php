<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor credit terms (net 30/60/90): a PO can be paid on terms rather than up
 * front. The payment due date is set when goods are first received, and a daily
 * command reminds admins (in-app / email / WhatsApp) as it approaches/passes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vendors', 'default_payment_terms_days')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->unsignedSmallInteger('default_payment_terms_days')->default(0)->after('country');
            });
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_terms_days')->default(0)->after('incoterm'); // 0 = due on receipt
            $table->date('payment_due_date')->nullable()->after('eta');
            $table->dateTime('last_payment_reminder_at')->nullable()->after('payment_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_terms_days', 'payment_due_date', 'last_payment_reminder_at']);
        });
        if (Schema::hasColumn('vendors', 'default_payment_terms_days')) {
            Schema::table('vendors', fn (Blueprint $t) => $t->dropColumn('default_payment_terms_days'));
        }
    }
};
