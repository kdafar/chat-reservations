<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_audience_metrics');

        DB::statement(<<<'SQL'
CREATE VIEW vw_audience_metrics AS
WITH norm_bookings AS (
    SELECT
        REGEXP_REPLACE(COALESCE(msisdn, ''), '[^0-9]', '') AS msisdn,
        branch_id,
        party_size,
        status,
        CASE
            WHEN CONCAT(COALESCE(res_date, ''), ' ', COALESCE(res_time, '')) = '' THEN NULL
            WHEN CONCAT(COALESCE(res_date, ''), ' ', COALESCE(res_time, '')) LIKE '0000%' THEN NULL
            ELSE STR_TO_DATE(CONCAT(res_date, ' ', res_time), '%Y-%m-%d %H:%i:%s')
        END AS booking_at
    FROM bookings
),
agg_bookings AS (
    SELECT
        nb.msisdn,
        COUNT(*) AS bookings_count,
        SUM(CASE WHEN nb.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
        MAX(nb.booking_at) AS last_booking_at
    FROM norm_bookings nb
    GROUP BY nb.msisdn
),
last_booking AS (
    -- Ensure single latest booking row per msisdn
    SELECT msisdn, branch_id, party_size
    FROM (
        SELECT
            nb.msisdn, nb.branch_id, nb.party_size, nb.booking_at,
            ROW_NUMBER() OVER (
                PARTITION BY nb.msisdn
                ORDER BY nb.booking_at DESC, nb.branch_id DESC, nb.party_size DESC
            ) AS rn
        FROM norm_bookings nb
        WHERE nb.booking_at IS NOT NULL
    ) x
    WHERE rn = 1
),
norm_wa_logs AS (
    SELECT
        REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', '') AS msisdn,
        created_at,
        CASE
            WHEN JSON_VALID(payload) AND JSON_CONTAINS_PATH(payload, 'one', '$.entry[0].changes[0].value.messages') THEN 'in'
            WHEN JSON_VALID(payload) AND JSON_CONTAINS_PATH(payload, 'one', '$.entry[0].changes[0].value.statuses') THEN 'out'
            WHEN NOT JSON_VALID(payload) AND payload LIKE '%"messages"%' THEN 'in'
            WHEN NOT JSON_VALID(payload) AND payload LIKE '%"statuses"%' THEN 'out'
            ELSE NULL
        END AS direction
    FROM wa_message_logs
),
wa_agg AS (
    SELECT
        msisdn,
        FROM_UNIXTIME(MAX(CASE WHEN direction = 'in'  THEN NULLIF(UNIX_TIMESTAMP(created_at), 0) END)) AS last_wa_in_at,
        FROM_UNIXTIME(MAX(CASE WHEN direction = 'out' THEN NULLIF(UNIX_TIMESTAMP(created_at), 0) END)) AS last_wa_out_at,
        FROM_UNIXTIME(MAX(NULLIF(UNIX_TIMESTAMP(created_at), 0)))                                     AS last_wa_any_at
    FROM norm_wa_logs
    GROUP BY msisdn
),
norm_sessions AS (
    SELECT
        REGEXP_REPLACE(COALESCE(phone, ''), '[^0-9]', '') AS msisdn,
        FROM_UNIXTIME(NULLIF(UNIX_TIMESTAMP(last_interacted_at), 0)) AS session_last_interacted_at
    FROM whatsapp_sessions
),
sessions_agg AS (
    -- collapse to one latest session row per msisdn
    SELECT msisdn, MAX(session_last_interacted_at) AS session_last_interacted_at
    FROM norm_sessions
    GROUP BY msisdn
),
all_people AS (
    SELECT msisdn FROM agg_bookings
    UNION
    SELECT msisdn FROM wa_agg
    UNION
    SELECT msisdn FROM sessions_agg
)
SELECT
    CAST(p.msisdn AS CHAR(20)) AS msisdn,
    COALESCE(ab.bookings_count, 0)  AS bookings_count,
    COALESCE(ab.confirmed_count, 0) AS confirmed_count,
    ab.last_booking_at,
    lb.branch_id                    AS last_branch_id,
    lb.party_size                   AS last_party_size,
    wa.last_wa_in_at,
    wa.last_wa_out_at,
    sa.session_last_interacted_at,
    FROM_UNIXTIME(
        NULLIF(
            GREATEST(
                IFNULL(UNIX_TIMESTAMP(wa.last_wa_any_at), 0),
                IFNULL(UNIX_TIMESTAMP(ab.last_booking_at), 0),
                IFNULL(UNIX_TIMESTAMP(sa.session_last_interacted_at), 0)
            ),
            0
        )
    ) AS last_interaction_at
FROM all_people p
LEFT JOIN agg_bookings  ab ON ab.msisdn = p.msisdn
LEFT JOIN last_booking   lb ON lb.msisdn = p.msisdn
LEFT JOIN wa_agg         wa ON wa.msisdn = p.msisdn
LEFT JOIN sessions_agg   sa ON sa.msisdn = p.msisdn;
SQL);

    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vw_audience_metrics');
    }
};
