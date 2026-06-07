<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_sessions', 'flow_street')) {
                $table->string('flow_street')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_sessions', 'flow_block_id')) {
                $table->string('flow_block_id')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_sessions', 'flow_house_no')) {
                $table->string('flow_house_no')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn(['flow_street', 'flow_block_id', 'flow_house_no']);
        });
    }
};
