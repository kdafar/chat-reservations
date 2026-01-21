<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_terms', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->foreignId('branch_id')->nullable()
                ->constrained('branches')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->boolean('terms_required')->default(false);

            $t->string('label_en', 255)->default('I agree to the reservation terms');
            $t->string('label_ar', 255)->default('أوافق على شروط الحجز');

            $t->text('text_en')->nullable();
            $t->text('text_ar')->nullable();

            $t->timestamps();

            // Enforce 1 active row per branch (including global)
            $t->unique(['branch_id', 'is_active'], 'reservation_terms_branch_active_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_terms');
    }
};
