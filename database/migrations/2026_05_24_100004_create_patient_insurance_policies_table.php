<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_insurance_policies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('insurer_id')->constrained('insurers');
            $table->foreignId('plan_id')->constrained('insurance_plans');

            $table->string('policy_number', 100);

            // Insurer-side member identifier (often printed on the card).
            $table->string('member_id', 100)->nullable();

            // Physical/virtual card number when distinct from member_id.
            $table->string('card_number', 100)->nullable();

            // Policyholder name when the patient is a dependent.
            $table->string('holder_name', 255)->nullable();

            $table->enum('holder_relationship', ['self', 'spouse', 'child', 'parent', 'other'])
                ->default('self');

            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            $table->boolean('is_primary')->default(false);

            // 1 = primary, 2 = secondary; used to order coverage application.
            $table->unsignedTinyInteger('priority')->default(1);

            $table->enum('status', ['active', 'expired', 'suspended', 'cancelled'])
                ->default('active')
                ->index();

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
            $table->unique(['insurer_id', 'policy_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_insurance_policies');
    }
};
