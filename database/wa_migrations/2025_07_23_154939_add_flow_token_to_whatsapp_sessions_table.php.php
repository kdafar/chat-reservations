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
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            // UUID v4 → 36‑char string (xxxxxxxx‑xxxx‑xxxx‑xxxx‑xxxxxxxxxxxx)
            $table->char('flow_token', 36)
                ->nullable()
                ->unique()
                ->after('customer_phone_number');   // place it near the top

            // optional composite index if you often query “by phone then latest”
            $table->index(['customer_phone_number', 'updated_at'], 'idx_phone_updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropUnique(['flow_token']);
            $table->dropIndex('idx_phone_updated');
            $table->dropColumn('flow_token');
        });
    }
};
