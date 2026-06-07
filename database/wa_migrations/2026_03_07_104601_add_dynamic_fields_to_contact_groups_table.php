<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('contact_groups', 'group_type')) {
                $table->string('group_type', 20)->default('manual')->after('description');
            }
            if (! Schema::hasColumn('contact_groups', 'filters_json')) {
                $table->json('filters_json')->nullable()->after('group_type');
            }
            if (! Schema::hasColumn('contact_groups', 'auto_refresh')) {
                $table->boolean('auto_refresh')->default(false)->after('filters_json');
            }
            if (! Schema::hasColumn('contact_groups', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('auto_refresh');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_groups', function (Blueprint $table) {
            $table->dropColumn([
                'group_type',
                'filters_json',
                'auto_refresh',
                'last_synced_at',
            ]);
        });
    }
};
