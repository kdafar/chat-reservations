<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Store paths (Storage::url() will build public URLs)
            $table->string('cover_image_path')->nullable()->after('slug');
            $table->string('logo_path')->nullable()->after('cover_image_path');

            // If you want remote images too (optional):
            // $table->string('cover_image_url')->nullable()->after('logo_path');

            // Helpful indexes for filters/sorting
            $table->index(['is_available', 'open_for_delivery', 'open_for_pickup'], 'branches_availability_idx');
            $table->index(['city_id'], 'branches_city_idx');
            $table->index(['partner_id'], 'branches_partner_idx');
        });

        Schema::table('branch_block', function (Blueprint $table) {
            // Query speed for coverage checks & sorting by pivot fee
            $table->index(['branch_id', 'block_id'], 'bb_branch_block_idx');
            $table->index(['block_id', 'is_active'], 'bb_block_active_idx');
        });

        Schema::table('branch_service', function (Blueprint $table) {
            $table->index(['branch_id', 'service_id'], 'bs_branch_service_idx');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex('branches_availability_idx');
            $table->dropIndex('branches_city_idx');
            $table->dropIndex('branches_partner_idx');
            $table->dropUnique('branches_slug_unique');

            $table->dropColumn(['cover_image_path', 'logo_path']);
            // $table->dropColumn('cover_image_url');
        });

        Schema::table('branch_block', function (Blueprint $table) {
            $table->dropIndex('bb_branch_block_idx');
            $table->dropIndex('bb_block_active_idx');
        });

        Schema::table('branch_service', function (Blueprint $table) {
            $table->dropIndex('bs_branch_service_idx');
        });
    }
};
