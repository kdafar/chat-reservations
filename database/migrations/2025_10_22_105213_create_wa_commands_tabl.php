<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_commands', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');               // e.g., hi, start, reset, help
            $table->string('language')->nullable();  // 'en', 'ar', or null = any
            $table->string('action');                // reset|start|menu|jump
            $table->json('params')->nullable();      // { "state": "SELECT_BRANCH" } for jump
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['keyword', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_commands');
    }
};
