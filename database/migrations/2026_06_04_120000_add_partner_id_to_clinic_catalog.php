<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clinic catalog (items + packages) is owned by the CLINIC (partner), shared
 * across that clinic's branches. Add partner_id as the tenancy key; branch_id is
 * kept as an optional "only at this branch within the clinic" override.
 * (Stock stays per-branch on clinic_item_stocks — untouched.)
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['clinic_items', 'clinic_packages'] as $table) {
            if (Schema::hasColumn($table, 'partner_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('partner_id')->nullable()->after('id')->index();
                $t->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['clinic_items', 'clinic_packages'] as $table) {
            if (! Schema::hasColumn($table, 'partner_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['partner_id']);
                $t->dropColumn('partner_id');
            });
        }
    }
};
