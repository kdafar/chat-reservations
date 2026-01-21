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
        Schema::table('visits', function (Blueprint $table) {
            $table->string('source')->nullable()->after('booking_id');
            $table->string('booking_code')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('source', 'booking_code')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('booking_code', 'source')) {
                $table->dropColumn('booking_code');
            }
        });
    }
};
