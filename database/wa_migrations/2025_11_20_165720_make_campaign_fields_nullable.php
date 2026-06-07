<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotional_campaigns', function (Blueprint $table) {
            // Make message_template_id nullable since we use template_name from Meta API now
            $table->unsignedBigInteger('message_template_id')->nullable()->change();

            // Make restaurant_id nullable if you are sending as an Admin (SaaS owner)
            // If you are strictly multi-tenant, you might handle this differently.
            $table->unsignedBigInteger('restaurant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('promotional_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('message_template_id')->nullable(false)->change();
            $table->unsignedBigInteger('restaurant_id')->nullable(false)->change();
        });
    }
};
