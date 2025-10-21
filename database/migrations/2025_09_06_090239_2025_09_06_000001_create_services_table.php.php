<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->json('name');        // {en:"Food", ar:"مطاعم"}
            $t->string('slug')->unique(); // "food", "grocery", ...
            $t->string('icon')->nullable(); // heroicon/lucide key or image path
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
