<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable clinical "dot-phrases" / snippets that a doctor can tap to insert
 * into a visit's free-text fields (chief complaint, examination, diagnosis,
 * patient instructions, …). Two flavours, distinguished by `scope`:
 *   - clinic   : shared library, visible to every doctor (doctor_id NULL)
 *   - doctor   : a doctor's personal favourite (doctor_id set)
 * Nothing here touches the visits table — phrases only compose text into the
 * existing free-text columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinical_phrases', function (Blueprint $table) {
            $table->id();
            // Which clinical field this phrase belongs to.
            $table->string('field', 32)->index();
            // 'en' | 'ar' | null (null = show regardless of UI locale).
            $table->string('locale', 8)->nullable()->index();
            $table->string('label', 191);   // short chip text
            $table->text('body');            // text inserted into the field
            $table->string('scope', 16)->default('clinic')->index(); // 'clinic' | 'doctor'
            $table->unsignedBigInteger('doctor_id')->nullable()->index(); // set for personal phrases
            $table->unsignedBigInteger('branch_id')->nullable()->index(); // null = all branches
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('doctor_id')->references('id')->on('doctors')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            $table->index(['field', 'scope', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_phrases');
    }
};
