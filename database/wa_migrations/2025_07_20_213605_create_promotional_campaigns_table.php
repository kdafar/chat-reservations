<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the promotional_campaigns table to store and track
 * marketing campaigns sent to users.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('promotional_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // A friendly name for the campaign, e.g., "August Welcome Back"

            // Link to the message template that will be used for this campaign
            $table->foreignId('message_template_id')->constrained('message_templates');

            // The status of the campaign
            $table->string('status')->default('draft'); // e.g., draft, sending, completed, failed

            $table->integer('total_recipients')->default(0); // The number of users targeted
            $table->timestamp('sent_at')->nullable(); // When the campaign sending was initiated
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
        Schema::dropIfExists('promotional_campaigns');
    }
};
