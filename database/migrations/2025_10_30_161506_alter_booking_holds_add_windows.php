<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_holds', function (Blueprint $t) {
            $t->dateTime('res_start')->nullable()->after('res_time');
            $t->dateTime('res_end')->nullable()->after('res_start');

            // fast overlap scans
            $t->index(['branch_id', 'party_size', 'res_start', 'res_end'], 'holds_branch_size_window_idx');

            // stop same user from hoarding same exact slot
            $t->unique(['slot_key', 'msisdn'], 'holds_unique_user_slot');
        });
    }

    public function down(): void
    {
        Schema::table('booking_holds', function (Blueprint $t) {
            $t->dropUnique('holds_unique_user_slot');
            $t->dropIndex('holds_branch_size_window_idx');
            $t->dropColumn(['res_start', 'res_end']);
        });
    }
};
