<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reception's daily cash close: one row per branch per business day.
 *
 * `expected` is a snapshot of what the system had recorded when the day was
 * closed (per-method totals + counts, voids) so the report does not change
 * when a late payment or void lands afterwards. Every total is computed by
 * the server (CashCloseController), never taken from the client.
 * Additive only; nothing existing is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_closes')) {
            return;
        }

        Schema::create('cash_closes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->nullable()->index();
            $t->date('business_date');
            $t->foreignId('closed_by_user_id')->nullable()->index();
            $t->decimal('opening_float', 12, 3)->default(0);
            $t->json('expected')->nullable();
            $t->decimal('cash_expected', 12, 3)->default(0);
            $t->decimal('cash_counted', 12, 3)->default(0);
            $t->decimal('cash_diff', 12, 3)->default(0);
            $t->decimal('knet_system', 12, 3)->default(0);
            $t->decimal('knet_slip', 12, 3)->nullable();
            $t->decimal('knet_diff', 12, 3)->nullable();
            $t->json('denominations')->nullable();
            $t->text('note')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();

            $t->unique(['branch_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closes');
    }
};
