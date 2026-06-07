<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_sessions', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('status')->comment('True if user replied STOP');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn('is_blocked');
        });
    }
};
