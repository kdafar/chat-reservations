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
        Schema::table('bulk_invite_campaigns', function (Blueprint $t) {
            if (! Schema::hasColumn('bulk_invite_campaigns', 'template_details')) {
                $t->json('template_details')->nullable()->after('template_name');
            }
            if (! Schema::hasColumn('bulk_invite_campaigns', 'header_image_path')) {
                $t->string('header_image_path')->nullable()->after('template_details');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulk_invite_campaigns', function (Blueprint $t) {
            if (Schema::hasColumn('bulk_invite_campaigns', 'header_image_path')) {
                $t->dropColumn('header_image_path');
            }
            if (Schema::hasColumn('bulk_invite_campaigns', 'template_details')) {
                $t->dropColumn('template_details');
            }
        });
    }
};
