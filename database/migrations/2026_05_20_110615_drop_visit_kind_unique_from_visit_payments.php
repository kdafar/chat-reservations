<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visit_payments')) {
            return;
        }

        // Drop the (visit_id, kind) unique index from the earlier migration —
        // it blocks legitimate split / partial payments and is bypassable when
        // kind is null. Idempotency for online callbacks is handled by the
        // reference_no check in PaymentCallbackController.
        $driver = DB::connection()->getDriverName();
        $exists = false;

        if ($driver === 'mysql') {
            $exists = (bool) DB::selectOne(
                "SELECT 1 AS present
                   FROM information_schema.statistics
                  WHERE table_schema = DATABASE()
                    AND table_name   = 'visit_payments'
                    AND index_name   = 'visit_payments_visit_id_kind_unique'
                  LIMIT 1"
            );
        } elseif ($driver === 'sqlite') {
            $exists = collect(DB::select("PRAGMA index_list('visit_payments')"))
                ->contains(fn ($row) => $row->name === 'visit_payments_visit_id_kind_unique');
        }

        if ($exists) {
            Schema::table('visit_payments', function (Blueprint $table) {
                $table->dropUnique('visit_payments_visit_id_kind_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('visit_payments')) {
            return;
        }

        Schema::table('visit_payments', function (Blueprint $table) {
            $table->unique(['visit_id', 'kind'], 'visit_payments_visit_id_kind_unique');
        });
    }
};
