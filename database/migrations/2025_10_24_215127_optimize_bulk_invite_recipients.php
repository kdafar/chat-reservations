<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_invite_recipients', function (Blueprint $table) {
            // Prevent duplicates per campaign
            $table->unique(['bulk_invite_campaign_id', 'msisdn'], 'campaign_msisdn_unique');

            // Speed up list/filtering
            $table->index(['bulk_invite_campaign_id', 'status'], 'campaign_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_invite_recipients', function (Blueprint $table) {
            $table->dropUnique('campaign_msisdn_unique');
            $table->dropIndex('campaign_status_idx');
        });
    }
};
