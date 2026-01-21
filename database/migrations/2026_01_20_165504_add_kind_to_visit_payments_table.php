<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('visit_payments', 'kind')) {
                $table->string('kind', 32)->nullable()->index()->after('method');
            }
        });

        // Unique constraint to enforce idempotency: one "consultation" payment per visit.
        // We only add it if it doesn't already exist.
        Schema::table('visit_payments', function (Blueprint $table) {
            // Some DBs/framework versions don't expose a clean "hasIndex" check.
            // If you already have this index, this migration will fail.
            // If that’s a concern in your environment, tell me your DB + Laravel version
            // and I’ll give you a fully defensive index check.
            $table->unique(['visit_id', 'kind'], 'visit_payments_visit_id_kind_unique');
        });
    }

    public function down(): void
    {
        Schema::table('visit_payments', function (Blueprint $table) {
            // Drop unique first, then column
            $table->dropUnique('visit_payments_visit_id_kind_unique');

            if (Schema::hasColumn('visit_payments', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }
};
