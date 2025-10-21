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
        Schema::table('gateway_accounts', function (Blueprint $t) {
            if (! Schema::hasColumn('gateway_accounts', 'branch_id')) {
                $t->unsignedBigInteger('branch_id')->nullable()->index()->after('partner_id');
            }
            if (! Schema::hasColumn('gateway_accounts', 'service_id')) {
                $t->unsignedBigInteger('service_id')->nullable()->index()->after('branch_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gateway_accounts', function (Blueprint $t) {
            if (Schema::hasColumn('gateway_accounts', 'service_id')) {
                $t->dropColumn('service_id');
            }
            if (Schema::hasColumn('gateway_accounts', 'branch_id')) {
                $t->dropColumn('branch_id');
            }
        });
    }
};
