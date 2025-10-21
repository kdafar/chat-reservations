<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_menu', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('menu_id');
            $table->primary(['coupon_id', 'menu_id']);
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->foreign('menu_id')->references('id')->on('menus')->onDelete('cascade');
        });

        Schema::create('coupon_menu_section', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('menu_section_id');
            $table->primary(['coupon_id', 'menu_section_id']);
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->foreign('menu_section_id')->references('id')->on('menu_sections')->onDelete('cascade');
        });

        Schema::create('coupon_menu_item', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedBigInteger('menu_item_id');
            $table->primary(['coupon_id', 'menu_item_id']);
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->foreign('menu_item_id')->references('id')->on('menu_items')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_menu_item');
        Schema::dropIfExists('coupon_menu_section');
        Schema::dropIfExists('coupon_menu');
    }
};
