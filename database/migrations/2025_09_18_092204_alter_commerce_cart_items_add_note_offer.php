<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_cart_items', function (Blueprint $t) {
            $t->text('note')->nullable()->after('modifiers');
            $t->json('offer')->nullable()->after('note'); // optional
        });
    }

    public function down(): void
    {
        Schema::table('commerce_cart_items', function (Blueprint $t) {
            $t->dropColumn(['note', 'offer']);
        });
    }
};
