<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_coverage_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')
                ->constrained('insurance_plans')
                ->cascadeOnDelete();

            // Matches VisitPayment.kind so claim-line matching can be done by kind.
            $table->enum('kind', ['consultation', 'services', 'medicines', 'other']);

            // percentage    — insurer pays X% of the line
            // fixed         — insurer pays a fixed amount per visit for this kind
            // copay_amount  — patient pays a fixed copay; insurer pays the rest
            $table->enum('coverage_type', ['percentage', 'fixed', 'copay_amount']);

            // Meaning depends on coverage_type (percent value, fixed KWD, or copay KWD).
            $table->decimal('coverage_value', 12, 3);

            // Per-visit cap on insurer payable for this kind.
            $table->decimal('max_per_visit', 12, 3)->nullable();

            // Annual cap on insurer payable for this kind.
            $table->decimal('max_annual', 12, 3)->nullable();

            // If true, services of this kind require an approved preauth before claiming.
            $table->boolean('requires_preauth')->default(false);

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_coverage_rules');
    }
};
