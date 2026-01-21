<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_plans', function (Blueprint $table) {
            $table->id();

            // one follow-up plan per source visit (idempotent anchor)
            $table->unsignedBigInteger('source_visit_id')->index();
            $table->unsignedBigInteger('patient_id')->index();

            // optional snapshot/filters
            $table->unsignedBigInteger('doctor_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            $table->dateTime('suggested_at')->nullable()->index();
            $table->boolean('auto_create_booking')->default(false)->index();

            // optional link to created booking (if auto-created)
            $table->unsignedBigInteger('booking_id')->nullable()->index();

            // operational status (optional but useful; safe default)
            $table->string('status', 30)->default('open')->index(); // open|booked|cancelled|done

            $table->timestamps();

            // hard idempotency
            $table->unique('source_visit_id', 'follow_up_plans_source_visit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_plans');
    }
};
