<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_branch_id');
            $table->integer('city_id');
            $table->decimal('delivery_fee', 8, 3);
            $table->decimal('min_order_value', 8, 3)->default(0);
            $table->timestamps();
            $table->foreign('hub_branch_id')->references('id')->on('hub_branches')->onDelete('cascade');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_areas');
    }
};
