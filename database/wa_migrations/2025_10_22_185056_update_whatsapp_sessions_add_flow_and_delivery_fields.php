<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Ensure columns exist (with any type)
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_sessions', 'delivery_state_id')) {
                if (! Schema::hasColumn('whatsapp_sessions', 'delivery_state_id')) {
                    $table->unsignedSmallInteger('delivery_state_id')->nullable()->after('delivery_address');
                }
            }
            if (! Schema::hasColumn('whatsapp_sessions', 'delivery_city_id')) {
                if (! Schema::hasColumn('whatsapp_sessions', 'delivery_city_id')) {
                    $table->integer('delivery_city_id')->nullable()->after('delivery_state_id');
                }
            }
            if (! Schema::hasColumn('whatsapp_sessions', 'last_promotional_campaign_id')) {
                // Keep BIGINT UNSIGNED; typical campaigns.id is bigIncrements
                if (! Schema::hasColumn('whatsapp_sessions', 'last_promotional_campaign_id')) {
                    $table->unsignedBigInteger('last_promotional_campaign_id')->nullable()->after('promo_code');
                }
            }
        });

        // 2) Normalize types exactly (safe even if already correct)
        $p = DB::getTablePrefix();
        DB::statement("ALTER TABLE `{$p}whatsapp_sessions` MODIFY `delivery_state_id` SMALLINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `{$p}whatsapp_sessions` MODIFY `delivery_city_id` INT NULL");

        // 3) Add FKs (drop first if they exist)
        try {
            Schema::table('whatsapp_sessions', fn (Blueprint $t) => $t->dropForeign('whatsapp_sessions_delivery_state_id_foreign'));
        } catch (\Throwable $e) {
        }
        try {
            Schema::table('whatsapp_sessions', fn (Blueprint $t) => $t->dropForeign('whatsapp_sessions_delivery_city_id_foreign'));
        } catch (\Throwable $e) {
        }

        if (Schema::hasTable('state')) {
            DB::statement("ALTER TABLE `{$p}whatsapp_sessions`
                           ADD CONSTRAINT `{$p}whatsapp_sessions_delivery_state_id_foreign`
                           FOREIGN KEY (`delivery_state_id`) REFERENCES `{$p}state`(`id`)
                           ON DELETE SET NULL ON UPDATE CASCADE");
        }

        if (Schema::hasTable('city')) {
            DB::statement("ALTER TABLE `{$p}whatsapp_sessions`
                           ADD CONSTRAINT `{$p}whatsapp_sessions_delivery_city_id_foreign`
                           FOREIGN KEY (`delivery_city_id`) REFERENCES `{$p}city`(`id`)
                           ON DELETE SET NULL ON UPDATE CASCADE");
        }

        // (Optional) Only add campaign FK later after you confirm `promotional_campaigns.id` type
        // matches UNSIGNED BIGINT. Otherwise skip the FK for now.
    }

    public function down(): void
    {
        // Drop FKs if present
        try {
            Schema::table('whatsapp_sessions', fn (Blueprint $t) => $t->dropForeign('whatsapp_sessions_delivery_state_id_foreign'));
        } catch (\Throwable $e) {
        }
        try {
            Schema::table('whatsapp_sessions', fn (Blueprint $t) => $t->dropForeign('whatsapp_sessions_delivery_city_id_foreign'));
        } catch (\Throwable $e) {
        }

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_sessions', 'last_promotional_campaign_id')) {
                $table->dropColumn('last_promotional_campaign_id');
            }
            if (Schema::hasColumn('whatsapp_sessions', 'delivery_city_id')) {
                $table->dropColumn('delivery_city_id');
            }
            if (Schema::hasColumn('whatsapp_sessions', 'delivery_state_id')) {
                $table->dropColumn('delivery_state_id');
            }
        });
    }
};
