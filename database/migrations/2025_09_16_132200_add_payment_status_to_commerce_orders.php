<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commerce_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('commerce_orders', 'payment_status')) {
                $t->enum('payment_status', ['unpaid', 'paid', 'refunded', 'failed', 'partial'])
                    ->default('unpaid')
                    ->after('status')
                    ->index();
            }
        });

        Schema::table('commerce_payments', function (Blueprint $t) {
            if (! Schema::hasColumn('commerce_payments', 'error_message')) {
                $t->text('error_message')->nullable()->after('provider_payload');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commerce_orders', function (Blueprint $t) {
            if (Schema::hasColumn('commerce_orders', 'payment_status')) {
                $t->dropColumn('payment_status');
            }
        });

        Schema::table('commerce_payments', function (Blueprint $t) {
            if (Schema::hasColumn('commerce_payments', 'error_message')) {
                $t->dropColumn('error_message');
            }
        });
    }
};
