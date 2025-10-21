<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('branches', 'city_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $t) {
            // indexes first (safe if exist)
            if (! Schema::hasIndex('branches', 'branches_city_id_index')) {
                $t->index('city_id');
            }
            if (! Schema::hasIndex('branches', 'branches_block_id_index')) {
                $t->index('block_id');
            }

            // add FKs if not present
            $t->foreign('city_id')->references('id')->on('cities')->nullOnDelete();
            $t->foreign('block_id')->references('id')->on('blocks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('branches', 'city_id')) {
            return;
        }

        Schema::table('branches', function (Blueprint $t) {
            try {
                $t->dropForeign(['city_id']);
            } catch (\Throwable $e) {
            }
            try {
                $t->dropForeign(['block_id']);
            } catch (\Throwable $e) {
            }
            try {
                $t->dropIndex(['city_id']);
            } catch (\Throwable $e) {
            }
            try {
                $t->dropIndex(['block_id']);
            } catch (\Throwable $e) {
            }
        });
    }
};
