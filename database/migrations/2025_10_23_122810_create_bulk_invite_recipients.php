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
        Schema::create('bulk_invite_recipients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bulk_invite_campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable(); // when picked from system users
            $t->string('msisdn');                 // normalized E.164 (+965…)
            $t->string('name')->nullable();
            $t->string('locale')->nullable();     // per-recipient 'en'/'ar'
            $t->enum('source', ['system', 'excel'])->default('system');
            $t->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $t->string('wa_message_id')->nullable(); // idempotency + tracking
            $t->text('error_message')->nullable();
            $t->timestamps();

            $t->unique(['bulk_invite_campaign_id', 'msisdn']); // dedupe within a campaign
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_invite_recipients');
    }
};
