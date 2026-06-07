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
        Schema::create('point_usages', function (Blueprint $table) {
            $table->id();
            // Who triggered this usage? (Nullable, because sometimes the System acts automatically)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // How many points were used?
            $table->integer('points')->default(0);

            // What was the event? (e.g., 'fleet_alert', 'whatsapp_bot_interaction', 'campaign')
            $table->string('event_type');

            // JSON column for extra details (e.g., message_id, recipient_number)
            $table->json('meta')->nullable();

            $table->timestamps();

            // Index for faster calculation of total usage
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_usages');
    }
};
