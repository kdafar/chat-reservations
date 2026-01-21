<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dateTime('res_start')->nullable()->after('res_time');
            $t->dateTime('res_end')->nullable()->after('res_start');
            $t->index(['branch_id', 'res_start', 'res_end']);
        });

        // Optional lightweight backfill for res_start from existing fields
        DB::statement("
            UPDATE bookings
            SET res_start = STR_TO_DATE(CONCAT(res_date,' ',res_time), '%Y-%m-%d %H:%i:%s')
            WHERE res_start IS NULL AND res_date IS NOT NULL AND res_time IS NOT NULL
        ");
        // res_end will be set on next update/confirm per rule's slot_length
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropIndex(['branch_id', 'res_start', 'res_end']);
            $t->dropColumn(['res_start', 'res_end']);
        });
    }
};
