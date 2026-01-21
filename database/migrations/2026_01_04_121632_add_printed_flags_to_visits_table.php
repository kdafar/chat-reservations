<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->boolean('is_prescriptions_printed')->default(false)->after('prescriptions');
            $table->boolean('is_labs_printed')->default(false)->after('lab_requests');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['is_prescriptions_printed', 'is_labs_printed']);
        });
    }
};
