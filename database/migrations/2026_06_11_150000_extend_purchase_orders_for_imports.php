<?php

use App\Models\Accounting\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades the basic purchase module into a full international-procurement
 * system: multi-currency POs (with exchange rate → KWD for the books), landed
 * costs (freight/customs/clearance/insurance) capitalised into inventory, a
 * submit→approve→send→acknowledge→receive→close lifecycle, shipment / ETA
 * tracking, incoterms, and per-line country of origin. Adds the
 * "2015 Import Costs Payable" GL account that landed costs accrue to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Currency / FX
            $table->string('currency', 3)->default('KWD')->after('branch_id');
            $table->decimal('exchange_rate', 16, 8)->default(1)->after('currency'); // KWD per 1 unit of currency
            $table->string('incoterm', 16)->nullable()->after('exchange_rate');      // EXW, FOB, CIF, DDP…

            // Money (goods in PO currency; *_kwd + landed + grand in KWD)
            $table->decimal('goods_total', 16, 3)->default(0)->after('subtotal');     // foreign currency
            $table->decimal('goods_total_kwd', 16, 3)->default(0)->after('goods_total');
            $table->decimal('freight_amount', 14, 3)->default(0)->after('goods_total_kwd');     // landed, KWD
            $table->decimal('customs_amount', 14, 3)->default(0)->after('freight_amount');
            $table->decimal('clearance_amount', 14, 3)->default(0)->after('customs_amount');
            $table->decimal('insurance_amount', 14, 3)->default(0)->after('clearance_amount');
            $table->decimal('other_charges_amount', 14, 3)->default(0)->after('insurance_amount');
            $table->decimal('landed_total', 14, 3)->default(0)->after('other_charges_amount');  // sum of landed, KWD
            // `total` (already present) = grand total KWD = goods_total_kwd + landed_total

            // Lifecycle audit
            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->after('created_by_user_id');
            $table->dateTime('submitted_at')->nullable()->after('submitted_by_user_id');
            $table->unsignedBigInteger('rejected_by_user_id')->nullable()->after('approved_at');
            $table->dateTime('rejected_at')->nullable()->after('rejected_by_user_id');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->unsignedBigInteger('sent_by_user_id')->nullable()->after('rejection_reason');
            $table->dateTime('sent_at')->nullable()->after('sent_by_user_id');
            $table->dateTime('acknowledged_at')->nullable()->after('sent_at');
            $table->string('vendor_reference', 191)->nullable()->after('acknowledged_at'); // vendor's PO/quote ref
            $table->dateTime('closed_at')->nullable()->after('vendor_reference');

            // Shipment / import logistics
            $table->string('carrier', 191)->nullable()->after('closed_at');
            $table->string('tracking_no', 191)->nullable()->after('carrier');
            $table->string('container_no', 191)->nullable()->after('tracking_no');
            $table->date('ship_date')->nullable()->after('container_no');
            $table->date('eta')->nullable()->after('ship_date');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->string('country_of_origin', 64)->nullable()->after('clinic_item_id');
        });

        Schema::table('purchase_receipts', function (Blueprint $table) {
            // total_amount = goods value received (KWD, → AP). landed_amount =
            // allocated landed cost (KWD, → Import Costs Payable). Inventory
            // debit = total_amount + landed_amount.
            $table->decimal('landed_amount', 14, 3)->default(0)->after('total_amount');
        });

        if (! Schema::hasColumn('vendors', 'default_currency')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('default_currency', 3)->nullable()->after('tax_number');
                $table->string('country', 64)->nullable()->after('default_currency');
            });
        }

        // GL account landed/import costs accrue to (settled later via Expenses).
        $parent = Account::query()->where('code', '2000')->first();
        if (! Account::query()->where('code', '2015')->exists()) {
            Account::query()->create([
                'code' => '2015',
                'name' => 'Import Costs Payable',
                'type' => Account::TYPE_LIABILITY,
                'parent_id' => $parent?->id,
                'is_system' => true,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'currency', 'exchange_rate', 'incoterm', 'goods_total', 'goods_total_kwd',
                'freight_amount', 'customs_amount', 'clearance_amount', 'insurance_amount',
                'other_charges_amount', 'landed_total', 'submitted_by_user_id', 'submitted_at',
                'rejected_by_user_id', 'rejected_at', 'rejection_reason', 'sent_by_user_id',
                'sent_at', 'acknowledged_at', 'vendor_reference', 'closed_at', 'carrier',
                'tracking_no', 'container_no', 'ship_date', 'eta',
            ]);
        });
        Schema::table('purchase_order_lines', fn (Blueprint $t) => $t->dropColumn('country_of_origin'));
        Schema::table('purchase_receipts', fn (Blueprint $t) => $t->dropColumn('landed_amount'));
        DB::table('chart_of_accounts')->where('code', '2015')->delete();
    }
};
