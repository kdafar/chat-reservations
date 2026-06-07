<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base users table for the isolated WhatsApp module (wa connection).
 * Replaces the Wave/Devdojo-published users table. Carries the columns the
 * ported WhatsApp code reads: is_admin + the per-user Meta credential columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('verified')->default(true);
            $table->string('role')->nullable();
            // Per-user Meta WhatsApp credentials used by TenantWhatsAppService
            $table->string('whatsapp_token')->nullable();
            $table->string('whatsapp_api_token')->nullable();
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_business_acc_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
