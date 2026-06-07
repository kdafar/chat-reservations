<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            if (! Schema::hasColumn('ratings', 'order_number')) {
                $table->string('order_number')->nullable()->after('comment');
            }
            if (! Schema::hasColumn('ratings', 'whatsapp_phone')) {
                $table->string('whatsapp_phone')->nullable()->after('order_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropColumn(['order_number', 'whatsapp_phone']);
        });
    }
};
