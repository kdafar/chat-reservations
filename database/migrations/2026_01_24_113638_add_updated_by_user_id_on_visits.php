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

            // 1) visits.updated_by_user_id
            if (! Schema::hasColumn('visits', 'updated_by_user_id')) {
                $table->unsignedBigInteger('updated_by_user_id')->nullable()->after('accepted_by_user_id');

                $table->index('updated_by_user_id');

                $table->foreign('updated_by_user_id')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            }

            // 2) visits.queued_at (needed by ORDER BY queued_at)
            if (! Schema::hasColumn('visits', 'queued_at')) {
                // place after updated_by_user_id if it exists, otherwise after accepted_by_user_id
                $after = Schema::hasColumn('visits', 'updated_by_user_id') ? 'updated_by_user_id' : 'accepted_by_user_id';

                $table->timestamp('queued_at')->nullable()->after($after);
                $table->index('queued_at');
            }

            // Optional: only add if you actually use accepted_at in code
            if (! Schema::hasColumn('visits', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('accepted_by_user_id');
                $table->index('accepted_at');
            }
        });

        // FIX: query expects visit_stock_requests.updated_by_user_id
        Schema::table('visit_stock_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('visit_stock_requests', 'updated_by_user_id')) {
                // pick a sensible placement for your table; "after('updated_at')" is usually safe if it exists
                $after = Schema::hasColumn('visit_stock_requests', 'updated_at') ? 'updated_at' : null;

                if ($after) {
                    $table->unsignedBigInteger('updated_by_user_id')->nullable()->after($after);
                } else {
                    $table->unsignedBigInteger('updated_by_user_id')->nullable();
                }

                $table->index('updated_by_user_id');

                $table->foreign('updated_by_user_id')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // visits.updated_by_user_id / visits.queued_at / visits.accepted_at
        Schema::table('visits', function (Blueprint $table) {
            // Drop FK/index/column for updated_by_user_id (only if present)
            if (Schema::hasColumn('visits', 'updated_by_user_id')) {
                try {
                    $table->dropForeign(['updated_by_user_id']);
                } catch (\Throwable) {
                    // ignore
                }

                try {
                    $table->dropIndex(['updated_by_user_id']);
                } catch (\Throwable) {
                    // ignore
                }

                try {
                    $table->dropColumn('updated_by_user_id');
                } catch (\Throwable) {
                    // ignore
                }
            }

            // Drop queued_at (only if present)
            if (Schema::hasColumn('visits', 'queued_at')) {
                try {
                    $table->dropIndex(['queued_at']);
                } catch (\Throwable) {
                    // ignore
                }

                try {
                    $table->dropColumn('queued_at');
                } catch (\Throwable) {
                    // ignore
                }
            }

            // Drop accepted_at (only if present)
            if (Schema::hasColumn('visits', 'accepted_at')) {
                try {
                    $table->dropIndex(['accepted_at']);
                } catch (\Throwable) {
                    // ignore
                }

                try {
                    $table->dropColumn('accepted_at');
                } catch (\Throwable) {
                    // ignore
                }
            }
        });

        // visit_stock_requests.updated_by_user_id
        Schema::table('visit_stock_requests', function (Blueprint $table) {
            if (Schema::hasColumn('visit_stock_requests', 'updated_by_user_id')) {
                try {
                    $table->dropForeign(['updated_by_user_id']);
                } catch (\Throwable) {
                    // ignore
                }

                try {
                    $table->dropIndex(['updated_by_user_id']);
                } catch (\Throwable) {
                    // ignore
                }

                try {
                    $table->dropColumn('updated_by_user_id');
                } catch (\Throwable) {
                    // ignore
                }
            }
        });
    }
};
