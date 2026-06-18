<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More per-entity GL account links (sibling of the partners/branches/gateway
 * migration). NULL = fall back to the built-in default code; the posting engine
 * (App\Services\Accounting\ChartOfAccounts) reads these, most-specific winning.
 *
 *   insurers.ar_account_id           -> that insurer's AR sub-account (else 1140)
 *   clinic_items.inventory_account_id-> that item's inventory account (else 1150)
 *   clinic_items.cogs_account_id     -> that item's cost-of-goods account (else 5120)
 *   services.revenue_account_id      -> that service's revenue account (else by kind)
 */
return new class extends Migration
{
    /** @var array<string, array<string>> table => columns */
    private array $map = [
        'insurers' => ['ar_account_id'],
        'clinic_items' => ['inventory_account_id', 'cogs_account_id'],
        'services' => ['revenue_account_id'],
    ];

    public function up(): void
    {
        foreach ($this->map as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        continue;
                    }
                    $t->unsignedBigInteger($col)->nullable();
                    $t->foreign($col)->references('id')->on('chart_of_accounts')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->map as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $col) {
                    if (! Schema::hasColumn($table, $col)) {
                        continue;
                    }
                    $t->dropForeign([$col]);
                    $t->dropColumn($col);
                }
            });
        }
    }
};
