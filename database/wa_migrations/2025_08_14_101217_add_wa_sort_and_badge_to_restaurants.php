<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'whatsapp_sort_order')) {
                $table->integer('whatsapp_sort_order')->default(0)->after('is_visible_on_whatsapp');
            }
            if (! Schema::hasColumn('restaurants', 'badge_active')) {
                $table->boolean('badge_active')->default(false)->after('whatsapp_sort_order');
            }
            $table->string('badge_emoji', 16)->nullable()->after('badge_active'); // emoji-safe (utf8mb4)
            if (! Schema::hasColumn('restaurants', 'badge_label_en')) {
                $table->string('badge_label_en', 32)->nullable()->after('badge_emoji');
            }
            if (! Schema::hasColumn('restaurants', 'badge_label_ar')) {
                $table->string('badge_label_ar', 32)->nullable()->after('badge_label_en');
            }

            $table->index(['is_visible_on_whatsapp', 'whatsapp_sort_order', 'id'], 'idx_wa_visible_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex('idx_wa_visible_order');
            $table->dropColumn([
                'whatsapp_sort_order',
                'badge_active',
                'badge_emoji',
                'badge_label_en',
                'badge_label_ar',
            ]);
        });
    }
};
