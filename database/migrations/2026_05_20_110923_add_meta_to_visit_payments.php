<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visit_payments')) {
            return;
        }

        if (! Schema::hasColumn('visit_payments', 'meta')) {
            Schema::table('visit_payments', function (Blueprint $table) {
                $table->json('meta')->nullable()->after('reference_no');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('visit_payments')) {
            return;
        }

        if (Schema::hasColumn('visit_payments', 'meta')) {
            Schema::table('visit_payments', function (Blueprint $table) {
                $table->dropColumn('meta');
            });
        }
    }
};
