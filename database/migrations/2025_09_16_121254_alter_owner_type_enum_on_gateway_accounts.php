<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Expand allowed values
        DB::statement("
            ALTER TABLE `gateway_accounts`
            MODIFY `owner_type`
            ENUM('system','partner','branch','service')
            NOT NULL
            DEFAULT 'system'
        ");

        // 2) Fix any invalid / empty / null values
        DB::statement("
            UPDATE `gateway_accounts`
            SET `owner_type` = 'system'
            WHERE `owner_type` NOT IN ('system','partner','branch','service') OR `owner_type` IS NULL
        ");

        // 3) Backfill owner_type from scoped IDs (in case some rows were saved before)
        DB::statement("
            UPDATE `gateway_accounts`
            SET `owner_type` = CASE
                WHEN `branch_id`  IS NOT NULL THEN 'branch'
                WHEN `service_id` IS NOT NULL THEN 'service'
                WHEN `partner_id` IS NOT NULL THEN 'partner'
                ELSE 'system'
            END
        ");
    }

    public function down(): void
    {
        // If you need to roll back, shrink enum (any branch/service will be forced to 'system')
        DB::statement("
            UPDATE `gateway_accounts`
            SET `owner_type` = CASE
                WHEN `partner_id` IS NOT NULL THEN 'partner'
                ELSE 'system'
            END
        ");

        DB::statement("
            ALTER TABLE `gateway_accounts`
            MODIFY `owner_type`
            ENUM('system','partner')
            NOT NULL
            DEFAULT 'system'
        ");
    }
};
