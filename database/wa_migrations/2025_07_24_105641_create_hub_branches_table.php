<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hub_branches', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('restaurant_id')->nullable()->index();
            $table->foreign('restaurant_id')->references('id')->on('restaurants')->nullOnDelete();
            // The key coming from restaurant side to identify branch uniquely
            $table->string('external_key')->unique(); // e.g. "trebiancoexpress" or "branch_12"

            // Optional: also store restaurant domain or restaurant_id if you have it
            $table->string('restaurant_domain')->nullable()->index();

            // Names
            $table->string('name_en')->nullable();
            $table->string('name_ar')->nullable();

            // WhatsApp phone digits only
            $table->string('wa_phone', 20)->nullable();

            // Optional override logo
            $table->string('logo_url')->nullable();

            // Active flags
            $table->boolean('is_active')->default(true);

            // Future-proof metadata
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_branches');
    }
};
