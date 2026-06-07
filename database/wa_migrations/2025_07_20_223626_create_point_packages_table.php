<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('point_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Starter Pack"
            $table->text('description')->nullable(); // e.g., "Perfect for small campaigns"
            $table->unsignedInteger('points'); // The number of points in the package
            $table->decimal('price', 8, 3); // The price of the package (e.g., 10.000 KWD)
            $table->string('currency', 3)->default('KWD');
            $table->boolean('is_active')->default(true); // To show/hide packages
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('point_packages');
    }
};
