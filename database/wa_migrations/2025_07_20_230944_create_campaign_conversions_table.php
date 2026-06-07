<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('campaign_conversions', function (Blueprint $table) {
            $table->id();
            // Link to the campaign that was sent
            $table->foreignId('promotional_campaign_id')->constrained('promotional_campaigns');
            // Link to the user session that received the campaign
            $table->foreignId('whatsapp_session_id')->constrained('whatsapp_sessions');
            // Store the ID of the order that was created after receiving the campaign
            $table->string('order_id_from_restaurant');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('campaign_conversions');
    }
};
