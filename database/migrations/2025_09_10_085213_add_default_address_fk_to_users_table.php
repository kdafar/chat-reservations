<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Now that addresses table exists, add the FK
            $table->foreignId('default_address_id')
                ->nullable()
                ->after('last_login_ip')
                ->constrained('addresses')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_address_id']);
            $table->dropColumn('default_address_id');
        });
    }
};
