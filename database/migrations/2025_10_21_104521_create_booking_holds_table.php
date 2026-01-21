<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('msisdn', 32)->index();
            $table->string('slot_key', 80)->unique(); // e.g. 2025-11-03@19:30@4@BRANCHID
            $table->date('res_date')->index();
            $table->time('res_time')->index();
            $table->unsignedTinyInteger('party_size');
            $table->timestamp('expires_at')->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};
