<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('wa_message_id')->unique(); // incoming message id (wamid)
            $table->string('phone')->index();
            $table->json('payload')->nullable();
            $table->string('status')->default('processed'); // processed|duplicate|error
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_logs');
    }
};
