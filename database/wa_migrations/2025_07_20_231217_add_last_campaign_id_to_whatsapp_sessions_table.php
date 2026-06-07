<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // This column will store the ID of the last campaign a user received.
            // It's nullable because not all users will have received a campaign.
            // --- FIX: Placing the column after 'selected_restaurant_id' which exists on this table. ---
            if (! Schema::hasColumn('whatsapp_sessions', 'last_promotional_campaign_id')) {
                $table->foreignId('last_promotional_campaign_id')->nullable()->after('selected_restaurant_id')->constrained('promotional_campaigns')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropForeign(['last_promotional_campaign_id']);
            $table->dropColumn('last_promotional_campaign_id');
        });
    }
};
