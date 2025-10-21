<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Contact & verification
            $table->string('phone', 50)->nullable()->after('email');
            $table->string('phone_country_code', 8)->nullable()->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');

            // Management
            $table->enum('status', ['active', 'inactive', 'suspended', 'banned'])
                ->default('active')
                ->after('remember_token');

            $table->boolean('marketing_opt_in')->default(false)->after('status');

            // Analytics
            $table->timestamp('last_login_at')->nullable()->after('marketing_opt_in');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            // If you prefer global phone uniqueness (common in GCC apps)
            $table->unique(['phone', 'phone_country_code'], 'users_phone_country_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_country_unique');

            $table->dropColumn([
                'phone',
                'phone_country_code',
                'phone_verified_at',
                'status',
                'marketing_opt_in',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
