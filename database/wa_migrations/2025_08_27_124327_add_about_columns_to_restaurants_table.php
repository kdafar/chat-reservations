<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'about_desc')) {
                $table->json('about_desc')->nullable()->after('description');
            }
            if (! Schema::hasColumn('restaurants', 'open_hours')) {
                $table->json('open_hours')->nullable()->after('about_desc');
            }
            if (! Schema::hasColumn('restaurants', 'website')) {
                $table->string('website')->nullable()->after('open_hours');
            }
            if (! Schema::hasColumn('restaurants', 'phone')) {
                $table->string('phone')->nullable()->after('website');
            }
            if (! Schema::hasColumn('restaurants', 'about_logo_path')) {
                $table->string('about_logo_path')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('restaurants', 'synonyms')) {
                $table->json('synonyms')->nullable()->after('about_logo_path');
            }
            if (! Schema::hasColumn('restaurants', 'about_enabled')) {
                $table->boolean('about_enabled')->default(true)->after('synonyms');
            }

            // Preferred template for this restaurant (locale-resolved via 'code')
            if (! Schema::hasColumn('restaurants', 'about_template_id')) {
                $table->unsignedBigInteger('about_template_id')->nullable()->after('about_enabled');
            }

            $table->foreign('about_template_id')
                ->references('id')->on('about_templates')->nullOnDelete();

            $table->index(['about_enabled']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropForeign(['about_template_id']);
            $table->dropColumn([
                'about_desc', 'open_hours', 'website', 'phone',
                'about_logo_path', 'synonyms', 'about_enabled', 'about_template_id',
            ]);
        });
    }
};
