<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_areas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('city_id');
            $t->unsignedBigInteger('block_id')->nullable();
            $t->decimal('delivery_fee', 10, 3)->default(0);
            $t->decimal('min_order_value', 10, 3)->default(0);
            $t->timestamps();
            $t->unique(['branch_id', 'city_id', 'block_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_areas');
    }
};
