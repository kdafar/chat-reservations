<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->string('qr_token', 32)->unique()->nullable();
            $t->foreignId('table_id')->nullable()->constrained('restaurant_tables');
            $t->timestamp('checked_in_at')->nullable(); // when guest arrived & seated
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $t) {
            $t->dropConstrainedForeignId('table_id');
            $t->dropColumn(['qr_token', 'checked_in_at']);
        });
    }
};
