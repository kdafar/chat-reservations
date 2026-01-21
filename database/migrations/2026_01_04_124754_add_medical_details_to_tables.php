<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add Legal/Contact info to Partners (The Brand)
        Schema::table('partners', function (Blueprint $table) {
            $table->string('website')->nullable()->after('slug');
            $table->string('email')->nullable()->after('website');
            $table->string('license_number')->nullable()->after('email'); // Commercial Registration / MOH License
            $table->text('footer_text')->nullable()->after('logo_path'); // Default footer for prints
        });

        // 2. Add Specifics to Branches (The Location)
        Schema::table('branches', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->string('license_number')->nullable()->after('email'); // Branch specific medical license
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['website', 'email', 'license_number', 'footer_text']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['email', 'license_number']);
        });
    }
};
