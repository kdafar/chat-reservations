<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_sessions', 'direct_intent_restaurant_id')) {
                $table->unsignedBigInteger('direct_intent_restaurant_id')->nullable()->default(null);
            }
            if (! Schema::hasColumn('whatsapp_sessions', 'direct_intent_cuisine_id')) {
                $table->unsignedBigInteger('direct_intent_cuisine_id')->nullable()->default(null);
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn(['direct_intent_restaurant_id', 'direct_intent_cuisine_id']);
        });
    }
};
