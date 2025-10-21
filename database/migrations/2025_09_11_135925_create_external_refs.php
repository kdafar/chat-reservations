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
        Schema::create('external_refs', function (Blueprint $table) {
            $table->id();
            $table->string('source');          // provider key (generic_json)
            $table->string('entity');          // menu|section|item|group|option
            $table->string('external_id');     // vendor ID
            $table->morphs('local');           // local_type, local_id
            $table->timestamps();
            $table->unique(['source', 'entity', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_refs');
    }
};
