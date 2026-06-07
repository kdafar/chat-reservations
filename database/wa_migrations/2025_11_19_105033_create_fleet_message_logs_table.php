<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fleet_message_logs', function (Blueprint $table) {
            $table->id();

            // The Fleet Customer (User) who sent the message
            $table->unsignedBigInteger('user_id');

            // The WhatsApp number used to send (Sender ID)
            $table->string('from_number')->nullable()->index();

            // The Recipient number
            $table->string('to_number')->index();

            // Optional: Message content (consider privacy/size)
            $table->text('message_body')->nullable();

            // The template used (if applicable)
            $table->string('template_name')->nullable();

            // Cost of this specific message in points
            $table->integer('points_cost')->default(1);

            // Status: sent, delivered, failed, queued
            $table->string('status')->default('queued');

            // External Message ID (from WhatsApp API)
            $table->string('provider_message_id')->nullable();

            $table->timestamps();

            // Foreign key to your users table
            // $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fleet_message_logs');
    }
};
