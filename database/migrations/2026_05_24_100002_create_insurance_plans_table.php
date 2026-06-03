<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('insurer_id')
                ->constrained('insurers')
                ->cascadeOnDelete();

            $table->string('name', 255);
            $table->string('name_ar', 255)->nullable();

            // Insurer-scoped plan code (e.g. "GOLD", "SILVER"). Unique per insurer.
            $table->string('code', 50);

            // Tier label for grouping/filtering (e.g. "platinum", "gold").
            $table->string('tier', 50)->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['insurer_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_plans');
    }
};
