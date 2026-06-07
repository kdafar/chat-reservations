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
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_session_id')->unique()->constrained('whatsapp_sessions')->onDelete('cascade');
            $table->string('full_name')->nullable();
            $table->string('phone_number')->unique(); // Store for quick lookup, link to WhatsappSession by session_id
            $table->string('default_delivery_address')->nullable();
            $table->string('default_apartment_number')->nullable();
            $table->text('notes')->nullable(); // General profile notes

            $table->json('addresses')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
