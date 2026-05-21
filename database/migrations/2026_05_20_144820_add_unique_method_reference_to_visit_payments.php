<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-level idempotency for gateway callbacks (audit follow-up review).
     *
     * The PaymentCallbackController uses updateOrCreate() keyed on
     * (method, reference_no), but without a DB-level unique constraint two
     * concurrent webhook deliveries can both pass the "doesn't exist" check
     * and both insert a row. Adding the unique index makes the second insert
     * fail with SQLSTATE 23000, which the controller now catches and treats
     * as idempotent success.
     *
     * Both MySQL and SQLite treat NULL as distinct in unique indexes, so cash
     * payments (reference_no = NULL) coexist freely. Only non-null tuples
     * are constrained — exactly what we want.
     */
    public function up(): void
    {
        if (! Schema::hasTable('visit_payments')) {
            return;
        }

        Schema::table('visit_payments', function (Blueprint $table) {
            $table->unique(['method', 'reference_no'], 'visit_payments_method_reference_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visit_payments')) {
            return;
        }

        Schema::table('visit_payments', function (Blueprint $table) {
            $table->dropUnique('visit_payments_method_reference_unique');
        });
    }
};
