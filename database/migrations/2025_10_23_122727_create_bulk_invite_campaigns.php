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
        Schema::create('bulk_invite_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('template_name'); // e.g. settings('whatsapp.templates.invite') || 'barfres_invite'
            $t->json('template_variables')->nullable(); // {"restaurant":"Barfres", "cta":"Book Now"}
            $t->string('header_media_type')->nullable(); // image|video|none
            $t->string('header_media_id')->nullable();   // WA media id (preferred)
            $t->string('header_media_url')->nullable();  // fallback link
            $t->string('default_locale')->default('en'); // 'en' or 'ar'
            $t->timestamp('scheduled_at')->nullable();   // null => send now
            $t->enum('status', ['draft', 'scheduled', 'running', 'paused', 'completed', 'failed'])->default('draft');
            $t->unsignedInteger('send_rate_per_min')->default(600); // throttle
            $t->unsignedInteger('total_recipients')->default(0);
            $t->unsignedInteger('sent_count')->default(0);
            $t->unsignedInteger('failed_count')->default(0);
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_invite_campaigns');
    }
};
