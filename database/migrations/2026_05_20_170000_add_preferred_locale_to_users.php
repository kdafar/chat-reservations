<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user UI language preference.
     *
     * Supported values: 'en' | 'ar'. NULL means "use app default" — the
     * SetLocaleFromUser middleware falls back to config('app.locale').
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        if (Schema::hasColumn('users', 'preferred_locale')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_locale', 5)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        if (! Schema::hasColumn('users', 'preferred_locale')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferred_locale');
        });
    }
};
