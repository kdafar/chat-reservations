<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuisines', function (Blueprint $t) {
            $t->id();
            $t->json('name');   // {en, ar}
            $t->string('slug')->unique();
            $t->string('image_path')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('branch_cuisine', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('cuisine_id')->constrained()->cascadeOnDelete();
            $t->unique(['branch_id', 'cuisine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_cuisine');
        Schema::dropIfExists('cuisines');
    }
};
