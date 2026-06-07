<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Settings key/value table for the module (replaces Wave's settings table).
 * Backs the Wave\Setting shim used by the WhatsApp services.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->longText('value')->nullable();
            $table->string('display_name')->nullable();
            $table->string('type')->nullable();
            $table->string('category')->default('general')->index();
            $table->integer('order')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
