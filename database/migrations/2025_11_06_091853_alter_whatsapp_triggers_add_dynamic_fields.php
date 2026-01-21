<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_triggers', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_triggers', 'response_type')) {
                // text, image_url, document_url, buttons, list, template, flow
                $table->string('response_type')->default('text')->after('response_message_ar');
            }
            if (! Schema::hasColumn('whatsapp_triggers', 'response_meta')) {
                $table->json('response_meta')->nullable()->after('response_type');
            }
            // (optional) small perf gains for lookups:
            if (! Schema::hasColumn('whatsapp_triggers', 'keyword')) {
                // already exists in your table; keep as-is
            }
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_triggers', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_triggers', 'response_meta')) {
                $table->dropColumn('response_meta');
            }
            if (Schema::hasColumn('whatsapp_triggers', 'response_type')) {
                $table->dropColumn('response_type');
            }
        });
    }
};
