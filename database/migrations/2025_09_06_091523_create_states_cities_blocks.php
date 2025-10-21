<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $t) {
            $t->id();
            $t->json('name');           // {en, ar}
            $t->string('slug')->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('cities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('state_id')->constrained('states')->cascadeOnDelete();
            $t->json('name');           // {en, ar}
            $t->string('slug')->unique();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['state_id', 'is_active']);
        });

        Schema::create('blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $t->json('name');           // {en:"Block 1", ar:"قطعة 1"} or area name
            $t->string('code')->nullable(); // e.g., "1", "2A"
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['city_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
    }
};
