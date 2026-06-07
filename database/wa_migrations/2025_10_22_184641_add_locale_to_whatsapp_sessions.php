<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_sessions', 'locale')) {
                if (! Schema::hasColumn('whatsapp_sessions', 'locale')) {
                    $table->string('locale', 8)->default('en')->after('status');
                }
                $table->index('locale');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_sessions', 'locale')) {
                $table->dropIndex(['locale']);
                $table->dropColumn('locale');
            }
        });
    }
};
