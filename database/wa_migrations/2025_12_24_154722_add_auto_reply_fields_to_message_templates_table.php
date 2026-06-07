<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('message_templates', 'is_auto_reply')) {
                $table->boolean('is_auto_reply')->default(false)->after('status');
            }
            if (! Schema::hasColumn('message_templates', 'triggers')) {
                $table->json('triggers')->nullable()->comment('Keywords like ["water", "wells"]')->after('is_auto_reply');
            }
            if (! Schema::hasColumn('message_templates', 'campaign_media_url')) {
                $table->string('campaign_media_url')->nullable()->after('triggers');
            }
            if (! Schema::hasColumn('message_templates', 'campaign_link')) {
                $table->string('campaign_link')->nullable()->after('campaign_media_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn(['is_auto_reply', 'triggers', 'campaign_media_url', 'campaign_link']);
        });
    }
};
