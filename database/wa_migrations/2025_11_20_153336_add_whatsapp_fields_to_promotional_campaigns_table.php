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
        Schema::table('promotional_campaigns', function (Blueprint $table) {
            // Basic template info
            if (! Schema::hasColumn('promotional_campaigns', 'template_name')) {
                $table->string('template_name')->nullable()->after('message_template_id');
            }
            if (! Schema::hasColumn('promotional_campaigns', 'template_details')) {
                $table->json('template_details')->nullable()->after('template_name');
            }
            if (! Schema::hasColumn('promotional_campaigns', 'template_variables')) {
                $table->json('template_variables')->nullable()->after('template_details');
            }

            // Header media
            if (! Schema::hasColumn('promotional_campaigns', 'header_image_path')) {
                $table->string('header_image_path')->nullable()->after('template_variables');
            }

            // Locale + scheduling
            if (! Schema::hasColumn('promotional_campaigns', 'default_locale')) {
                $table->string('default_locale', 10)->nullable()->after('header_image_path');
            }
            if (! Schema::hasColumn('promotional_campaigns', 'scheduled_at')) {
                $table->dateTime('scheduled_at')->nullable()->after('sent_at');
            }

            // Throttling
            if (! Schema::hasColumn('promotional_campaigns', 'send_rate_per_min')) {
                $table->integer('send_rate_per_min')->nullable()->default(600)->after('scheduled_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotional_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'template_name',
                'template_details',
                'template_variables',
                'header_image_path',
                'default_locale',
                'scheduled_at',
                'send_rate_per_min',
            ]);
        });
    }
};
