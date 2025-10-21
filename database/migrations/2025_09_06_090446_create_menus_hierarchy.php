<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->json('name');
            $t->json('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('menu_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $t->json('name');
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_section_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete(); // denormalized for faster queries
            $t->json('name');
            $t->json('description')->nullable();
            $t->string('image_path')->nullable();
            $t->string('sku')->nullable();
            $t->decimal('price', 10, 3);
            $t->boolean('is_available')->default(true);
            $t->timestamps();
            $t->index(['branch_id', 'is_available']);
        });

        Schema::create('modifier_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->json('name');
            $t->boolean('is_required')->default(false);
            $t->unsignedInteger('min_choices')->default(0);
            $t->unsignedInteger('max_choices')->default(0);
            $t->timestamps();
        });

        Schema::create('modifier_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $t->json('name');
            $t->decimal('price_delta', 10, 3)->default(0);
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        Schema::create('item_modifier_option', function (Blueprint $t) {
            $t->id();
            $t->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $t->unique(['menu_item_id', 'modifier_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_modifier_option');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_sections');
        Schema::dropIfExists('menus');
    }
};
