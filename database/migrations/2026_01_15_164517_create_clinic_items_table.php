<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_items', function (Blueprint $table) {
            $table->id();

            // Branch-aware catalog:
            // NULL = shared item usable in any branch
            // branch_id = branch-specific item/pricing
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // Translatable (json: {en, ar})
            $table->json('name');

            // 'consumable' | 'service'
            $table->string('type', 50)->index();

            $table->decimal('default_cost', 12, 3)->default(0);
            $table->decimal('default_price', 12, 3)->default(0);

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->foreign('branch_id')
                ->references('id')->on('branches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_items');
    }
};
