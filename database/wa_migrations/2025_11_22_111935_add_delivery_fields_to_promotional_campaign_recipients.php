<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotional_campaign_recipients', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->change(); // if needed

            $table->timestamp('sent_at')->nullable()->after('status');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');

            $table->string('wa_error_code')->nullable()->after('error_message');
            $table->string('wa_error_title')->nullable()->after('wa_error_code');
            $table->json('wa_status_payload')->nullable()->after('wa_error_title');

            // optional but nice:
            $table->string('wa_conversation_id')->nullable()->after('wa_message_id');
            $table->string('wa_pricing_model')->nullable()->after('wa_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('promotional_campaign_recipients', function (Blueprint $table) {
            $table->dropColumn([
                'sent_at',
                'delivered_at',
                'read_at',
                'wa_error_code',
                'wa_error_title',
                'wa_status_payload',
                'wa_conversation_id',
                'wa_pricing_model',
            ]);
        });
    }
};
