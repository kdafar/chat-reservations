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
        Schema::table('support_email_logs', function (Blueprint $table) {
            // 1. Add the 'type' column to distinguish between broadcasts and system notifications
            $table->string('type')->default('broadcast')->after('status');

            // 2. Make 'broadcast_id' nullable because system notifications don't have one
            $table->unsignedBigInteger('broadcast_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_email_logs', function (Blueprint $table) {
            $table->dropColumn('type');

            // Note: We cannot easily revert broadcast_id to non-nullable if null values exist,
            // so usually we leave it nullable or delete the null rows first.
            // For safety, we will just leave it nullable in the down method.
        });
    }
};
