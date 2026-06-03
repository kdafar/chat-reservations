<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();

            $table->string('channel', 16);   // whatsapp | sms | email
            $table->string('purpose', 32);   // booking | login | ...
            $table->string('recipient', 64); // normalized msisdn or email
            $table->string('code_hash', 255);

            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();

            $table->string('ip', 64)->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            // Hot path: find latest unverified+unexpired code for (purpose, recipient).
            $table->index(['purpose', 'recipient', 'verified_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
