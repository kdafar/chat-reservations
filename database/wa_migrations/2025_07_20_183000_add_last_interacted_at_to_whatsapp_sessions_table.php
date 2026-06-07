<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // We are adding a new column to an existing table.
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_sessions', 'last_interacted_at')) {
                $table->timestamp('last_interacted_at')->nullable()->after('updated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     * This ensures we can undo the change if needed.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn('last_interacted_at');
        });
    }
};
