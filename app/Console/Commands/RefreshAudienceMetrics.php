<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshAudienceMetrics extends Command
{
    protected $signature = 'audience:refresh';

    protected $description = 'Refresh materialized audience_metrics from vw_audience_metrics (deduped & sanitized)';

    public function handle(): int
    {
        $this->info('Refreshing audience_metrics...');

        // Relax sql_mode (session-only): avoid NO_ZERO_DATE noise while we coerce values ourselves
        $origMode = (string) (DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m ?? '');
        $relaxed = collect(explode(',', $origMode))
            ->reject(fn ($m) => in_array(trim($m), ['NO_ZERO_DATE', 'NO_ZERO_IN_DATE']))
            ->implode(',');
        DB::statement("SET SESSION sql_mode = '".addslashes($relaxed)."'");

        try {
            // Clear table
            DB::table('audience_metrics')->truncate();

            // Ranked, sanitized insert (one row per msisdn)
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
    DATE_FORMAT(last_booking_at,          '%Y-%m-%d %H:%i:%s') AS last_booking_at_s,
    DATE_FORMAT(last_wa_in_at,            '%Y-%m-%d %H:%i:%s') AS last_wa_in_at_s,
    DATE_FORMAT(last_wa_out_at,           '%Y-%m-%d %H:%i:%s') AS last_wa_out_at_s,
    DATE_FORMAT(session_last_interacted_at, '%Y-%m-%d %H:%i:%s') AS session_last_interacted_at_s,
    DATE_FORMAT(last_interaction_at,      '%Y-%m-%d %H:%i:%s') AS last_interaction_at_s
  FROM vw_audience_metrics
),
ranked AS (
  SELECT
    b.*,
    ROW_NUMBER() OVER (
      PARTITION BY b.msisdn
      ORDER BY
        STR_TO_DATE(NULLIF(NULLIF(b.last_interaction_at_s,      '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC,
        STR_TO_DATE(NULLIF(NULLIF(b.session_last_interacted_at_s, '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC,
        STR_TO_DATE(NULLIF(NULLIF(b.last_wa_in_at_s,            '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC,
        STR_TO_DATE(NULLIF(NULLIF(b.last_wa_out_at_s,           '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC,
        STR_TO_DATE(NULLIF(NULLIF(b.last_booking_at_s,          '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s') DESC
    ) AS rn
  FROM base b
)
SELECT
  msisdn,
  bookings_count,
  confirmed_count,
  STR_TO_DATE(NULLIF(NULLIF(last_booking_at_s,          '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s'),
  last_branch_id,
  last_party_size,
  STR_TO_DATE(NULLIF(NULLIF(last_wa_in_at_s,            '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s'),
  STR_TO_DATE(NULLIF(NULLIF(last_wa_out_at_s,           '0000-00-0Next 00:00:00'), ''), '%Y-%m-%d %H:%i:%s'),
  STR_TO_DATE(NULLIF(NULLIF(session_last_interacted_at_s, '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s'),
  STR_TO_DATE(NULLIF(NULLIF(last_interaction_at_s,      '0000-00-00 00:00:00'), ''), '%Y-%m-%d %H:%i:%s'),
  NOW()
FROM ranked
WHERE rn = 1
SQL
            );

            $count = DB::table('audience_metrics')->count();
            $this->info("Audience metrics refreshed ({$count} rows).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Refresh failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            DB::statement("SET SESSION sql_mode = '".addslashes($origMode)."'");
        }
    }
}
