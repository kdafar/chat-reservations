<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Doctor accept workflow (add-only)
            $table->unsignedBigInteger('accepted_by_user_id')->nullable()->after('doctor_id');
            $table->dateTime('accepted_at')->nullable()->after('accepted_by_user_id');

            // Optional: queue timestamp (recommended)
            $table->dateTime('queued_at')->nullable()->after('checked_in_at');

            $table->index('accepted_by_user_id');
            $table->index('accepted_at');
            $table->index('queued_at');

            $table->foreign('accepted_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Drop FK first
            try {
                $table->dropForeign(['accepted_by_user_id']);
            } catch (\Throwable) {
                // ignore (some envs might not have it)
            }

            $table->dropIndex(['accepted_by_user_id']);
            $table->dropIndex(['accepted_at']);
            $table->dropIndex(['queued_at']);

            $table->dropColumn(['accepted_by_user_id', 'accepted_at', 'queued_at']);
        });
    }
};
