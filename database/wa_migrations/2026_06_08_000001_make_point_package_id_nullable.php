<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow point_purchases.point_package_id to be NULL so the v2 admin can record
 * manual point top-ups that aren't tied to a sellable PointPackage.
 */
return new class extends Migration
{
    protected $connection = 'wa';

    public function up(): void
    {
        $conn = DB::connection('wa');
        $table = $conn->getTablePrefix().'point_purchases';
        $conn->statement("ALTER TABLE `{$table}` MODIFY `point_package_id` BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        // Intentionally left as a no-op — reverting to NOT NULL would break manual top-ups.
    }
};
