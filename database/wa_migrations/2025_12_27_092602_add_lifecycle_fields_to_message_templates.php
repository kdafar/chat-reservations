<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            // "draft" = saved locally only
            // "published" = sent to Meta (waiting for approval)
            if (! Schema::hasColumn('message_templates', 'local_status')) {
                $table->string('local_status')->default('draft')->after('status');
            }

            // If Meta rejects the template, we store the reason here
            if (! Schema::hasColumn('message_templates', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('local_status');
            }

            // To track when we last checked Meta for an update
            if (! Schema::hasColumn('message_templates', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn(['local_status', 'rejection_reason', 'last_synced_at']);
        });
    }
};
