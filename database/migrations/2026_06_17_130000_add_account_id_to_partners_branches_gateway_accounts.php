<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-entity GL account links: lets the accountant pin a specific chart account
 * to an individual partner, branch, or gateway account.
 *
 *   branches.account_id        -> the branch's cash / operating account
 *   partners.account_id        -> the clinic's default (services) revenue account
 *   gateway_accounts.account_id-> the settlement / clearing account a gateway's
 *                                 receipts land in
 *
 * NULL = fall back to the global posting map, then the built-in EVA default.
 * The posting engine (App\Services\Accounting\ChartOfAccounts) reads these,
 * with the most-specific link winning.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['partners', 'branches', 'gateway_accounts'] as $table) {
            if (Schema::hasColumn($table, 'account_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('account_id')->nullable()->after('id');
                $t->foreign('account_id')
                    ->references('id')->on('chart_of_accounts')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['partners', 'branches', 'gateway_accounts'] as $table) {
            if (! Schema::hasColumn($table, 'account_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['account_id']);
                $t->dropColumn('account_id');
            });
        }
    }
};
