<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A clinic (partner) can designate one of its branches as the central
 * hub/warehouse that holds bulk stock and dispatches it to the other branches.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('branches', 'is_hub')) {
            return;
        }
        Schema::table('branches', function (Blueprint $t) {
            $t->boolean('is_hub')->default(false)->after('partner_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('branches', 'is_hub')) {
            return;
        }
        Schema::table('branches', function (Blueprint $t) {
            $t->dropColumn('is_hub');
        });
    }
};
