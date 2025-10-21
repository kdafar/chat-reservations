<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(User::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // If you have City/Block tables, keep these FKs; otherwise make them nullable unsigned bigInteger.
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('block_id')->constrained()->restrictOnDelete();

            $table->string('label', 40)->nullable();      // Home / Work / Other
            $table->string('street', 190);
            $table->string('building', 190)->nullable();
            $table->string('house', 190)->nullable();
            $table->string('apartment', 190)->nullable();
            $table->string('floor', 50)->nullable();
            $table->string('notes', 500)->nullable();

            // Kuwait-friendly precision
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
            $table->index(['city_id', 'block_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
