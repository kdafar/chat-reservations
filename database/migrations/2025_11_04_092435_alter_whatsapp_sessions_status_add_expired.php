<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL ENUM MODIFY — skip on non-MySQL drivers (SQLite test env).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Add 'expired' (and keep existing values)
        DB::statement("
            ALTER TABLE `whatsapp_sessions`
            MODIFY `status` ENUM('active','completed','abandoned','expired')
            NOT NULL DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        // If any rows are 'expired', map them back before shrinking the enum
        DB::transaction(function () {
            DB::table('whatsapp_sessions')
                ->where('status', 'expired')
                ->update(['status' => 'abandoned']);
            DB::statement("
                ALTER TABLE `whatsapp_sessions`
                MODIFY `status` ENUM('active','completed','abandoned')
                NOT NULL DEFAULT 'active'
            ");
        });
    }
};
