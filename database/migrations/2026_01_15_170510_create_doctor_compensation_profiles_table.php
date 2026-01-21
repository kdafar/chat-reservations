<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_compensation_profiles', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('doctor_id')->index();

            // salary | percentage
            $table->string('type', 30)->default('percentage');

            // fees_only | net_profit
            $table->string('basis', 30)->default('fees_only');

            // used only when type = percentage
            $table->decimal('percentage_rate', 7, 3)->nullable(); // e.g., 30.000

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->foreign('doctor_id')
                ->references('id')->on('doctors')
                ->cascadeOnDelete();

            // enforce a single active profile per doctor (recommended)
            // If you later want history, keep multiple records but ensure only one active.
            $table->unique(['doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_compensation_profiles');
    }
};
