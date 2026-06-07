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
        Schema::create('point_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants');
            $table->foreignId('point_package_id')->constrained('point_packages');

            $table->unsignedInteger('points_purchased');
            $table->decimal('amount_paid', 8, 3);
            $table->string('currency', 3);

            $table->string('payment_gateway')->nullable(); // e.g., 'tap', 'stripe'
            $table->string('transaction_id')->nullable(); // The ID from the payment gateway
            $table->string('status')->default('pending'); // e.g., pending, completed, failed

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
        Schema::dropIfExists('point_purchases');
    }
};
