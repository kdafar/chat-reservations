<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurers', function (Blueprint $table) {
            $table->id();

            $table->string('name', 255);

            // Arabic display name for bilingual UI.
            $table->string('name_ar', 255)->nullable();

            // Short internal code (e.g. "GIG", "BUPA"). Unique across the org.
            $table->string('code', 50)->unique();

            // Tax registration / commercial license number for invoices.
            $table->string('tax_id', 50)->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();

            // Net N days — used to age receivables on the AR dashboard.
            $table->unsignedInteger('payment_terms_days')->default(30);

            $table->boolean('is_active')->default(true)->index();

            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurers');
    }
};
