<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete(); // assumes branches table exists
            $table->string('msisdn', 32)->index();           // WhatsApp phone
            $table->unsignedTinyInteger('party_size');       // 1..12 (or more if you like)
            $table->date('res_date')->index();
            $table->time('res_time')->index();
            $table->enum('status', ['draft', 'hold', 'confirmed', 'cancelled'])->default('confirmed')->index();
            $table->string('booking_code', 16)->unique();    // short confirmation code
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'res_date', 'res_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
