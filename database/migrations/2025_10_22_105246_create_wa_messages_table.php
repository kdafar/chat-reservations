<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->string('key');                 // booking.active_found, booking.ask_branch, etc.
            $table->string('language')->default('en'); // en or ar
            $table->text('text');                  // supports {vars}
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['key', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};
