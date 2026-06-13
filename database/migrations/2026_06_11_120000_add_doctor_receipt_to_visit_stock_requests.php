<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doctor receipt step for visit stock requests.
 *
 * Adds a "received" stage between fulfilment (store issues stock) and patient
 * billing: pending -> fulfilled (issued, awaiting receipt) -> received (doctor
 * confirmed; patient billed). Adds who/when received on the request and a
 * per-line received_qty so the doctor can short-confirm.
 *
 * Backfill: existing 'fulfilled' rows were already billed under the old
 * one-step behaviour, so promote them to 'received' (received_by/at mirror
 * fulfilled_by/at) and set each line's received_qty = qty_base, so they don't
 * resurface on the worklist as "awaiting receipt".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_stock_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('received_by_user_id')->nullable()->after('fulfilled_at');
            $table->timestamp('received_at')->nullable()->after('received_by_user_id');
        });

        Schema::table('visit_stock_request_lines', function (Blueprint $table) {
            // Null until the request is received. On receipt: 0..qty_base.
            $table->decimal('received_qty', 12, 4)->nullable()->after('qty_base');
        });

        // Promote already-billed 'fulfilled' rows to the new 'received' meaning.
        $fulfilledIds = DB::table('visit_stock_requests')
            ->where('status', 'fulfilled')
            ->pluck('id');

        if ($fulfilledIds->isNotEmpty()) {
            DB::table('visit_stock_requests')
                ->whereIn('id', $fulfilledIds)
                ->update([
                    'status' => 'received',
                    'received_by_user_id' => DB::raw('fulfilled_by_user_id'),
                    'received_at' => DB::raw('fulfilled_at'),
                ]);

            DB::table('visit_stock_request_lines')
                ->whereIn('visit_stock_request_id', $fulfilledIds)
                ->update(['received_qty' => DB::raw('qty_base')]);
        }
    }

    public function down(): void
    {
        // Revert the promoted rows back to 'fulfilled' before dropping columns.
        DB::table('visit_stock_requests')->where('status', 'received')->update(['status' => 'fulfilled']);

        Schema::table('visit_stock_requests', function (Blueprint $table) {
            $table->dropColumn(['received_by_user_id', 'received_at']);
        });

        Schema::table('visit_stock_request_lines', function (Blueprint $table) {
            $table->dropColumn('received_qty');
        });
    }
};
