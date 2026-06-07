<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotional_campaign_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('promotional_campaign_recipients', 'name')) {
                $table->string('name', 120)->nullable()->after('msisdn');
            }
            if (! Schema::hasColumn('promotional_campaign_recipients', 'locale')) {
                $table->string('locale', 10)->nullable()->after('name');
            }
            if (! Schema::hasColumn('promotional_campaign_recipients', 'source')) {
                $table->string('source', 32)->nullable()->default('system')->after('locale');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promotional_campaign_recipients', function (Blueprint $table) {
            $table->dropColumn(['name', 'locale', 'source']);
        });
    }
};
