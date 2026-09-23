<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The visit's day as the workspace records it — "called to Room 2",
 * "vitals taken", "3 tests ordered", "receipt printed" — so the timeline a
 * doctor or reception reads survives a refresh and answers "why did she wait
 * an hour?" the next day too. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visit_events')) {
            return;
        }
        Schema::create('visit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('visit_id')->index();
            $t->string('kind', 32);
            $t->string('text', 255);
            $t->foreignId('user_id')->nullable();
            $t->timestamp('at')->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_events');
    }
};
