<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (! Schema::hasColumn('doctors', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('branch_id')->index();
            }
        });

        // FK in a separate schema call to stay defensive on some MySQL setups
        Schema::table('doctors', function (Blueprint $table) {
            // Use explicit constraint name to avoid collisions
            if (Schema::hasColumn('doctors', 'user_id')) {
                $table->foreign('user_id', 'doctors_user_id_fk')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            if (Schema::hasColumn('doctors', 'user_id')) {
                // drop FK safely
                try {
                    $table->dropForeign('doctors_user_id_fk');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('user_id');
            }
        });
    }
};
