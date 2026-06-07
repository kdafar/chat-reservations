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
        Schema::table('fleet_message_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('fleet_message_logs', 'alert_event')) {
                $table->string('alert_event')->nullable()->after('template_name')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fleet_message_logs', function (Blueprint $table) {
            $table->dropColumn('alert_event');
        });
    }
};
