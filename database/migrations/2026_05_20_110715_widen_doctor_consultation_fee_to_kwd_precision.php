<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('doctors') || ! Schema::hasColumn('doctors', 'consultation_fee')) {
            return;
        }

        // KWD uses 3 decimals; every other money column in the system is decimal(12,3).
        // Doctor was decimal(10,2) which caused off-by-0.005 reconciliation drift.
        Schema::table('doctors', function (Blueprint $table) {
            $table->decimal('consultation_fee', 12, 3)->default(0)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('doctors') || ! Schema::hasColumn('doctors', 'consultation_fee')) {
            return;
        }

        Schema::table('doctors', function (Blueprint $table) {
            $table->decimal('consultation_fee', 10, 2)->default(0)->change();
        });
    }
};
