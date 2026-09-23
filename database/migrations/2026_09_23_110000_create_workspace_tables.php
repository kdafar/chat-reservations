<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage for the visit workspace (v3) features that had none:
 *
 *   order_sets    a doctor's saved treatment: drugs + lab tests + bill items
 *                 + a follow-up, applied to a visit in one click
 *   queue_calls   "please go to Room 2" — what the waiting-room screen shows
 *
 * Vitals, allergies and medical alerts need no table: visits.vitals,
 * patients.allergies and patients.medical_alerts already exist.
 * Additive only; nothing existing is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_sets')) {
            Schema::create('order_sets', function (Blueprint $t) {
                $t->id();
                $t->foreignId('owner_user_id')->nullable()->index();
                $t->foreignId('branch_id')->nullable()->index();
                $t->boolean('shared')->default(false);
                $t->string('name', 120);
                $t->json('drugs')->nullable();       // [{name, strength, dose, freq, dur}]
                $t->json('lab_test_ids')->nullable(); // [int]
                $t->json('items')->nullable();        // [{type: item|package, id}]
                $t->unsignedSmallInteger('follow_up_days')->nullable();
                $t->unsignedInteger('uses')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('queue_calls')) {
            Schema::create('queue_calls', function (Blueprint $t) {
                $t->id();
                $t->foreignId('visit_id')->index();
                $t->foreignId('branch_id')->nullable()->index();
                $t->string('ticket', 16);
                $t->string('display_name', 80);   // first name + initial only
                $t->string('room', 80)->nullable();
                $t->foreignId('called_by_user_id')->nullable();
                $t->timestamp('called_at')->index();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_calls');
        Schema::dropIfExists('order_sets');
    }
};
