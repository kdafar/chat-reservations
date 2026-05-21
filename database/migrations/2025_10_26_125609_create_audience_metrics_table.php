<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the materialized table
        Schema::create('audience_metrics', function (Blueprint $table) {
            $table->string('msisdn')->primary();
            $table->integer('bookings_count')->default(0);
            $table->integer('confirmed_count')->default(0);
            $table->timestamp('last_booking_at')->nullable();
            $table->unsignedBigInteger('last_branch_id')->nullable();
            $table->integer('last_party_size')->nullable();
            $table->timestamp('last_wa_in_at')->nullable();
            $table->timestamp('last_wa_out_at')->nullable();
            $table->timestamp('session_last_interacted_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('refreshed_at')->nullable();
        });

        // The data backfill below uses MySQL CTEs + window functions
        // + @@SESSION.sql_mode. Skip everything below on SQLite (test env).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // --- Relax sql_mode (session-only) to avoid NO_ZERO_DATE tripping mid-insert
        $origMode = (string) (DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m ?? '');
        $relaxed = collect(explode(',', $origMode))
            ->reject(fn ($m) => in_array(trim($m), ['NO_ZERO_DATE', 'NO_ZERO_IN_DATE']))
            ->implode(',');
        DB::statement("SET SESSION sql_mode = '".addslashes($relaxed)."'");

        try {
            DB::statement(<<<'SQL'
INSERT INTO audience_metrics
    (msisdn, bookings_count, confirmed_count, last_booking_at, last_branch_id,
     last_party_size, last_wa_in_at, last_wa_out_at, session_last_interacted_at,
     last_interaction_at, refreshed_at)
WITH base AS (
    SELECT
        CAST(msisdn AS CHAR(20)) AS msisdn,
        bookings_count,
        confirmed_count,
        last_branch_id,
        last_party_size,
        DATE_FORMAT(last_booking_at,            '%Y-%m-%d %H:%i:%s') AS last_booking_at_s,
        DATE_FORMAT(last_wa_in_at,              '%Y-%m-%d %H:%i:%s') AS last_wa_in_at_s,
        DATE_FORMAT(last_wa_out_at,             '%Y-%m-%d %H:%i:%s') AS last_wa_out_at_s,
        DATE_FORMAT(session_last_interacted_at, '%Y-%m-%d %H:%i:%s') AS session_last_interacted_at_s,
        DATE_FORMAT(last_interaction_at,        '%Y-%m-%d %H:%i:%s') AS last_interaction_at_s
    FROM vw_audience_metrics
),
ranked AS (
    SELECT
        b.*,
        ROW_NUMBER() OVER (
            PARTITION BY b.msisdn
            ORDER BY
                STR_TO_DATE(NULLIF(NULLIF(b.last_interaction_at_s,        '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC,
                STR_TO_DATE(NULLIF(NULLIF(b.session_last_interacted_at_s, '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC,
                STR_TO_DATE(NULLIF(NULLIF(b.last_wa_in_at_s,              '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC,
                STR_TO_DATE(NULLIF(NULLIF(b.last_wa_out_at_s,             '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC,
                STR_TO_DATE(NULLIF(NULLIF(b.last_booking_at_s,            '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC
        ) AS rn
    FROM base b
)
SELECT
    msisdn,
    bookings_count,
    confirmed_count,
    STR_TO_DATE(NULLIF(NULLIF(last_booking_at_s,            '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') AS last_booking_at,
    last_branch_id,
    last_party_size,
    STR_TO_DATE(NULLIF(NULLIF(last_wa_in_at_s,              '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') AS last_wa_in_at,
    STR_TO_DATE(NULLIF(NULLIF(last_wa_out_at_s,             '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') AS last_wa_out_at,
    STR_TO_DATE(NULLIF(NULLIF(session_last_interacted_at_s, '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') AS session_last_interacted_at,
    STR_TO_DATE(NULLIF(NULLIF(last_interaction_at_s,        '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') AS last_interaction_at,
    NOW() AS refreshed_at
FROM ranked
WHERE rn = 1
SQL);
        } finally {
            DB::statement("SET SESSION sql_mode = '".addslashes($origMode)."'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_metrics');
    }
};
