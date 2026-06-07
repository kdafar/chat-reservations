<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_engagement_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('campaigns_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('replied_count')->default(0);

            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamp('last_pending_at')->nullable();
            $table->timestamp('last_replied_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->boolean('is_active')->default(false);

            $table->timestamps();

            $table->unique('contact_id');
            $table->index('is_active');
            $table->index('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_engagement_stats');
    }
};
