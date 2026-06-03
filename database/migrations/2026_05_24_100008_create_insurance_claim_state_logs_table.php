<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claim_state_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('claim_id')
                ->constrained('insurance_claims')
                ->cascadeOnDelete();

            // Nullable to capture the initial creation transition (null -> draft).
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);

            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->dateTime('changed_at');

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['claim_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_state_logs');
    }
};
