<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_sessions', 'last_interacted_at')) {
                $table->timestamp('last_interacted_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropIndex(['last_interacted_at']); // generates name whatsapp_sessions_last_interacted_at_index
            $table->dropColumn('last_interacted_at');
        });
    }
};
