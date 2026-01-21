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
        Schema::create('whatsapp_flow_states', function (Blueprint $t) {
            $t->id();
            $t->string('flow_token')->unique();
            $t->string('msisdn')->index();
            $t->string('screen')->default('APPOINTMENT');
            $t->json('data')->nullable(); // {branch_id, party_size, res_date, res_time, slot_key, name, phone, email, notes...}
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_states');
    }
};
