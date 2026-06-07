<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // The user who got the refund
            $table->integer('points'); // Amount refunded
            $table->string('reason')->nullable(); // e.g., 'undeliverable', 'failed'
            $table->string('wamid')->nullable()->index(); // WhatsApp Message ID for reference
            $table->unsignedBigInteger('campaign_id')->nullable(); // Linked campaign
            $table->json('original_meta')->nullable(); // Backup of the deleted record's metadata
            $table->timestamp('refunded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_refunds');
    }
};
