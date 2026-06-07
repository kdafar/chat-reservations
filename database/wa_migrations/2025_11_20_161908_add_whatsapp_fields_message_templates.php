<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            // Meta template ID from Facebook
            if (! Schema::hasColumn('message_templates', 'meta_id')) {
                $table->string('meta_id')->nullable()->after('id');
            }

            // Meta fields
            $table->string('category')->nullable()->after('name');          // MARKETING / UTILITY / AUTHENTICATION
            $table->string('language', 10)->nullable()->after('category');  // e.g. "en", "ar"
            $table->string('status', 32)->nullable()->after('language');    // e.g. APPROVED, REJECTED, etc.

            // Full components JSON from Meta
            if (! Schema::hasColumn('message_templates', 'components')) {
                $table->json('components')->nullable()->after('body');
            }

            // Handy extracted BODY text preview
            if (! Schema::hasColumn('message_templates', 'body_preview')) {
                $table->text('body_preview')->nullable()->after('components');
            }

            // Optional: index for faster lookups
            $table->index('meta_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropIndex(['meta_id']);
            $table->dropIndex(['name']);
            $table->dropColumn([
                'meta_id',
                'category',
                'language',
                'status',
                'components',
                'body_preview',
            ]);
        });
    }
};
