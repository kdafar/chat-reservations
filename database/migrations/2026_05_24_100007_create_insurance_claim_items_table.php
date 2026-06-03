<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_claim_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('claim_id')
                ->constrained('insurance_claims')
                ->cascadeOnDelete();

            // Polymorphic-ish link back to the source row (e.g. App\Models\VisitCharge)
            // or the string 'adhoc' for line items created directly on the claim.
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Mirrors VisitPayment.kind to drive coverage-rule matching.
            $table->enum('kind', ['consultation', 'services', 'medicines', 'other']);

            $table->string('label', 255);

            $table->decimal('qty', 10, 3)->default(1);

            // Unit price captured at claim time (source rows may change later).
            $table->decimal('unit_price_snapshot', 12, 3)->default(0);

            // qty * unit_price_snapshot
            $table->decimal('line_total', 12, 3)->default(0);

            // Amount billed to insurer for this line (after copay).
            $table->decimal('claimed_amount', 12, 3)->default(0);

            // Amount insurer ultimately approved for this line.
            $table->decimal('approved_amount', 12, 3)->default(0);

            $table->decimal('patient_copay_amount', 12, 3)->default(0);

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index('claim_id');
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_items');
    }
};
