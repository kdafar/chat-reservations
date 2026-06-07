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
        Schema::table('message_templates', function (Blueprint $table) {
            $table->json('auto_reply_data')
                ->nullable()
                ->after('campaign_link')
                ->comment('Stores dynamic variable values for auto-replies (e.g., {"header_1": "Hello", "body_1": "Order 123"})');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn('auto_reply_data');
        });
    }
};
